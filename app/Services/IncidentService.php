<?php

namespace App\Services;

use App\Enums\IncidentAction;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IncidentService {

	/*.s. Valida los datos y crea la incidencia abierta junto con su evento de creación en una única transacción. */
	public function changeStatus(
		Incident       $incident,
		IncidentStatus $target,
		User|string    $actor,
		?string        $reason = null,
	): Incident {

		$data = Validator::make($this->trimStrings([
			'user_name' => $actor instanceof User ? $actor->name : $actor,
			'reason'    => $reason,
		]), [
			'user_name' => ['required', 'string', 'max:120'],
			'reason'    => ['nullable', 'string', 'max:2000'],
		])->validate();

		return DB::transaction(function () use ($incident, $target, $data, $actor) {
			$current = Incident::query()->lockForUpdate()->findOrFail($incident->getKey());

			if ($current->status !== $incident->status) {
				throw ValidationException::withMessages([
					'status' => 'La incidencia ha cambiado. Actualiza los datos antes de continuar.',
				]);
			}

			if (!$current->status->canTransitionTo($target)) {
				throw ValidationException::withMessages([
					'status' => "No se permite pasar de {$current->status->label()} a {$target->label()}.",
				]);
			}

			$reopening = $current->status === IncidentStatus::Resolved;

			if ($reopening && ($data['reason'] ?? '') === '') {
				throw ValidationException::withMessages([
					'reason' => 'Indica un motivo para reabrir la incidencia.',
				]);
			}

			$previous = $current->status;
			$current->update(['status' => $target]);
			$current->logs()->create([
				'action'    => $reopening ? IncidentAction::Reopened : IncidentAction::StatusChanged,
				'old_value' => $previous->value,
				'new_value' => $target->value,
				'user_name' => $data['user_name'],
				'user_id'   => $actor instanceof User ? $actor->id : null,
				'metadata'  => isset($data['reason']) ? ['reason' => $data['reason']] : null,
			]);

			return $current;
		});
	}

	/*.s. Comprueba el estado observado y la transición permitida; guarda el cambio y su historial de forma atómica, exigiendo motivo al reabrir. */

	private function trimStrings(array $input): array {
		return array_map(fn($value) => is_string($value) ? trim($value) : $value, $input);
	}

	/*.s. Valida el texto y el autor y guarda el comentario junto con su evento en el historial dentro de una transacción. */

	public function create(array $input, ?User $actor = null): Incident {
		$data = Validator::make($this->trimStrings($input), [
			'title'          => ['required', 'string', 'max:180'],
			'description'    => ['required', 'string', 'max:10000'],
			'priority'       => ['required', Rule::enum(IncidentPriority::class)],
			'requester_name' => ['required', 'string', 'max:120'],
			'assigned_to'    => ['nullable', 'string', 'max:120'],
		])->validate();

		return DB::transaction(function () use ($data, $actor) {
			$incident = Incident::create([...$data, 'status' => IncidentStatus::Open]);
			$incident->logs()->create([
				'action'    => IncidentAction::Created,
				'new_value' => IncidentStatus::Open->value,
				'user_name' => $actor?->name ?? $data['requester_name'],
				'user_id'   => $actor?->id,
			]);

			return $incident;
		});
	}

	/*.s. Elimina los espacios de los extremos de los valores de texto sin modificar los demás tipos de datos. */

	public function addComment(Incident $incident, User|string $author, string $body): IncidentComment {
		$data = Validator::make($this->trimStrings([
			'author_name' => $author instanceof User ? $author->name : $author,
			'body'        => $body,
		]), [
			'author_name' => ['required', 'string', 'max:120'],
			'body'        => ['required', 'string', 'max:10000'],
		])->validate();

		return DB::transaction(function () use ($incident, $data, $author) {
			$current = Incident::query()->lockForUpdate()->findOrFail($incident->getKey());
			$comment = $current->comments()->create([...$data, 'user_id' => $author instanceof User ? $author->id : null]);
			$current->logs()->create([
				'action'    => IncidentAction::CommentAdded,
				'user_name' => $data['author_name'],
				'user_id'   => $author instanceof User ? $author->id : null,
				'metadata'  => ['comment_id' => $comment->id],
			]);

			return $comment;
		});
	}
}
