<?php

namespace App\Console\Commands;

use App\Services\IncidentAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuditIncidents extends Command
{
    protected $signature = 'incidents:audit
        {--dry-run : Mostrar el diagnóstico sin modificar datos (por defecto)}
        {--fix : Marcar únicamente duplicados exactos y registrar la corrección}
        {--incident= : Limitar a una incidencia por ID}
        {--actor=auditoria : Actor que firma las correcciones}';

    protected $description = 'Diagnosticar inconsistencias y marcar duplicados conservando la evidencia';

    /*.s. Valida las opciones, ejecuta la auditoría y muestra los hallazgos y el código de salida correspondiente. */
    public function handle(IncidentAuditService $audit): int
    {
        if ($this->option('fix') && $this->option('dry-run')) {
            $this->error('--fix y --dry-run son incompatibles.');

            return self::INVALID;
        }

        /*.s. Comprueba que el ID solicitado corresponda a una incidencia existente. */
        $id = $this->option('incident');
        if ($id !== null && (! ctype_digit($id) || (int) $id < 1 || ! DB::table('incidents')->where('id', $id)->exists())) {
            $this->error('El ID debe corresponder a una incidencia existente.');

            return self::INVALID;
        }

        $fix = (bool) $this->option('fix');
        $this->info($fix ? 'Aplicando correcciones seguras; los casos ambiguos quedan pendientes.' : 'DRY-RUN: diagnóstico sin modificar datos.');

        try {
            $result = $audit->run($fix, $id === null ? null : (int) $id, $this->option('actor'));
        } catch (ValidationException $exception) {
            $this->error(implode(' ', $exception->validator->errors()->all()));

            return self::INVALID;
        }

        if ($fix) {
            $this->info('Duplicados marcados: '.$result['repaired']);
        }
        if ($result['findings'] === []) {
            $this->info('Sin inconsistencias pendientes en las comprobaciones realizadas.');

            return self::SUCCESS;
        }

        $this->table(['Incidencia', 'Código', 'Log', 'Decisión', 'Detalle'], array_map(fn ($finding) => [
            $finding['incident_id'],
            $finding['code'],
            $finding['log_id'] ?? '-',
            $finding['fixable'] ? 'Marcar duplicado con --fix' : 'Revisión manual',
            $finding['detail'],
        ], $result['findings']));
        $this->warn('Hallazgos pendientes: '.count($result['findings']));

        return self::FAILURE;
    }
}
