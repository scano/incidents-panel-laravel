<?php

namespace App\Services;

use App\Enums\IncidentAction;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use stdClass;

class IncidentAuditService {
	/*.s. Revisa las incidencias por lotes y devuelve los hallazgos; si se solicita corregir, marca duplicados exactos con bloqueo y registra cada reparación. */
	/**
	 * @return array{findings: array, repaired: int}
	 */
	public function run(bool $fix = false, ?int $incidentId = null, string $actor = 'auditoria'): array {

		Validator::make(['actor' => trim($actor)], ['actor' => ['required', 'string', 'max:120']])->validate();
		$findings = [];
		$repaired = 0;

		$incidents = DB::table('incidents')
		               ->when($incidentId !== null, fn($query) => $query->where('id', $incidentId))
		               ->lazyById(100);

		foreach ($incidents as $incident) {

			if (!$fix) {
				array_push($findings, ...$this->inspect($incident, $this->logs($incident->id)));
				continue;
			}

			$result = DB::transaction(function () use ($incident, $actor) {
				$current = DB::table('incidents')->where('id', $incident->id)->lockForUpdate()->first();
				if ($current === null) {
					return ['findings' => [], 'repaired' => 0];
				}

				$logs  = $this->logs($current->id, lock: true);
				$plan  = $this->inspect($current, $logs);
				$fixed = 0;

				foreach ($plan as $finding) {
					if (!$finding['fixable']) {
						continue;
					}

					$original = $logs->firstWhere('id', $finding['log_id']);
					DB::table('incident_logs')->where('id', $original->id)->update([
						'duplicate_of_id' => $finding['duplicate_of_id'],
						'voided_at'       => now(),
					]);

					Incident::findOrFail($current->id)->logs()->create([
						'action'    => IncidentAction::DuplicateMarked,
						'user_name' => trim($actor),
						'metadata'  => [
							'duplicate_log_id' => $original->id,
							'canonical_log_id' => $finding['duplicate_of_id'],
							'reason'           => 'Evento de estado exacto y consecutivo, con la misma fecha, actor y metadatos.',
							'original'         => $this->eventData($original),
						],
					]);
					$fixed++;
				}

				return ['findings' => $this->inspect($current, $this->logs($current->id)), 'repaired' => $fixed];
			});

			array_push($findings, ...$result['findings']);
			$repaired += $result['repaired'];
		}

		return ['findings' => $findings, 'repaired' => $repaired];
	}

	/*.s. Obtiene los eventos de una incidencia en orden cronológico y, opcionalmente, bloquea las filas durante la corrección. */
	private function logs(int $incidentId, bool $lock = false): Collection {
		return DB::table('incident_logs')->where('incident_id', $incidentId)
		         ->orderBy('created_at')->orderBy('id')
		         ->when($lock, fn($query) => $query->lockForUpdate())->get();
	}

	/*.s. Analiza las filas sin convertirlas a enums para detectar estados desconocidos, eventos inválidos, duplicados y discrepancias con el historial. */
	private function inspect(stdClass $incident, Collection $logs): array {

		$findings      = [];
		$previous      = null;
		$hasResolution = false;

		//.s. Integridad del log
		foreach ($logs as $log) {

			$invalidMarker = false;
			if ($log->duplicate_of_id !== null || $log->voided_at !== null) {
				$canonical = $logs->firstWhere('id', $log->duplicate_of_id);

				if ($canonical
					&& $canonical->duplicate_of_id === null
					&& $canonical->id < $log->id
					&& $log->voided_at !== null
					&& $this->validStateLog($canonical)
					&& $this->eventData($canonical) === $this->eventData($log)) {
					continue;
				}

				$findings[]    = $this->finding($incident, 'invalid_duplicate_marker', $log->id, 'La marca de duplicado no referencia un evento original idéntico.');
				$invalidMarker = true;
			}

			if (in_array($log->action, [IncidentAction::CommentAdded->value, IncidentAction::DuplicateMarked->value], true)) {
				continue;
			}

			if (!$this->validStateLog($log)) {
				$findings[] = $this->finding($incident, 'invalid_log', $log->id, 'Acción, transición, actor o motivo de reapertura incoherentes.');
				$previous   = $log;
				continue;
			}

			//.s. Duplicado exacto
			if (!$invalidMarker
				&& $previous
				&& $previous->duplicate_of_id === null
				&& $previous->voided_at === null
				&& $this->validStateLog($previous)
				&& $this->eventData($previous) === $this->eventData($log)) {

				$findings[] = [
					...$this->finding($incident, 'duplicate_log', $log->id, "Duplicado exacto del log {$previous->id}; marcar sin borrar el original."),
					'fixable'         => true,
					'duplicate_of_id' => $previous->id,
				];

				continue;
			}

			if (
				/* .s. El primer registro del log */
				($previous === null && $log->action !== IncidentAction::Created->value)
				||
				/* .s. Ya no es el primero. La accion no puede ser Created o que el estado antenior del log no sea el nuevo del anterior (cascada)*/
				($previous !== null && ($log->action === IncidentAction::Created->value || $log->old_value !== $previous->new_value))
			) {

				$findings[] = $this->finding($incident, 'broken_chain', $log->id, 'La secuencia no empieza con creación o no continúa el estado anterior.');
			}

			$hasResolution = $hasResolution || $log->new_value === IncidentStatus::Resolved->value;
			$previous      = $log;
		}

		if (IncidentStatus::tryFrom($incident->status) === null) {
			$findings[] = $this->finding($incident, 'invalid_status', null, 'Estado actual desconocido.');
		}

		if ($incident->status === IncidentStatus::Resolved->value && !$hasResolution) {
			$findings[] = $this->finding($incident, 'resolved_without_log', null, 'Resuelta sin evento válido de resolución; faltan fecha y actor verificables.');
		}

		if ($previous === null) {
			$findings[] = $this->finding($incident, 'missing_history', null, 'No hay eventos de estado válidos para contrastar la incidencia.');
		} elseif ($incident->status !== $previous->new_value) {
			$findings[] = $this->finding($incident, 'status_mismatch', $previous->id, "Estado actual {$incident->status}; último estado registrado " . ($previous->new_value ?? '(vacío)') . '.');
		}

		return $findings;
	}

	/*.s. Comprueba que el evento tenga actor, metadatos y transición válidos, incluido el motivo obligatorio de reapertura. */
	private function validStateLog(stdClass $log): bool {

		$metadata = json_decode($log->metadata ?? 'null', true);
		if (json_last_error() !== JSON_ERROR_NONE || ($metadata !== null && !is_array($metadata))) {
			return false;
		}

		if (trim($log->user_name) === '') {
			return false;
		}

		if ($log->action === IncidentAction::Created->value) {
			return $log->old_value === null && $log->new_value === IncidentStatus::Open->value;
		}

		$from = IncidentStatus::tryFrom($log->old_value ?? '');
		$to   = IncidentStatus::tryFrom($log->new_value ?? '');
		if (!$from || !$to || !$from->canTransitionTo($to)) {
			return false;
		}

		if ($log->action === IncidentAction::Reopened->value) {
			return $from === IncidentStatus::Resolved && $to === IncidentStatus::Open
				&& is_array($metadata) && is_string($metadata['reason'] ?? null) && trim($metadata['reason']) !== '';
		}

		return $log->action === IncidentAction::StatusChanged->value && $from !== IncidentStatus::Resolved;
	}

	/*.s. Extrae los campos que deben coincidir para considerar dos eventos idénticos y conservar la evidencia de una reparación. */
	private function eventData(stdClass $log): array {
		return [
			'action'     => $log->action,
			'old_value'  => $log->old_value,
			'new_value'  => $log->new_value,
			'user_name'  => $log->user_name,
			'user_id'    => $log->user_id,
			'created_at' => $log->created_at,
			'metadata'   => $log->metadata,
		];
	}

	/*.s. Construye un hallazgo con la incidencia, el código, el log y el detalle; por defecto requiere revisión manual. */
	private function finding(stdClass $incident, string $code, ?int $logId, string $detail): array {
		return [
			'incident_id' => $incident->id,
			'code'        => $code,
			'log_id'      => $logId,
			'detail'      => $detail,
			'fixable'     => false
		];
	}
}
