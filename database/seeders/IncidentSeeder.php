<?php

namespace Database\Seeders;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\User;
use App\Services\IncidentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IncidentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(IncidentService $service): void
    {
        $this->call(UserSeeder::class);
        $ana = User::where('email', 'ana@example.test')->firstOrFail();
        $luis = User::where('email', 'luis@example.test')->firstOrFail();

        $examples = [
            ['No se puede exportar el informe', 'El informe mensual devuelve un error al descargarlo.', 'alta', IncidentStatus::Open],
            ['Acceso intermitente al panel', 'Algunos usuarios reciben un error al entrar.', 'critica', IncidentStatus::InReview],
            ['Sincronización detenida', 'Pendiente de respuesta del proveedor para continuar.', 'media', IncidentStatus::Blocked],
            ['Texto incorrecto en la cabecera', 'Se ha corregido el nombre del departamento.', 'baja', IncidentStatus::Resolved],
            ['La búsqueda no encuentra expedientes recientes', 'Los expedientes de hoy no aparecen al buscar por referencia.', 'alta', IncidentStatus::Open],
            ['Error al adjuntar documentos', 'La carga de archivos PDF termina sin guardar el documento.', 'media', IncidentStatus::InReview],
            ['Impresora de recepción sin conexión', 'Pendiente de que soporte revise la conexión de red.', 'media', IncidentStatus::Blocked],
            ['Fecha incorrecta en el justificante', 'Se ha ajustado la zona horaria del documento generado.', 'baja', IncidentStatus::Resolved],
            ['Notificaciones de seguimiento duplicadas', 'Se reciben dos avisos para el mismo cambio de expediente.', 'media', IncidentStatus::Open],
            ['El panel tarda en cargar', 'Se está revisando la consulta del listado en horas de mayor actividad.', 'alta', IncidentStatus::InReview],
            ['Certificado del proveedor caducado', 'La integración está detenida hasta que el proveedor renueve el certificado.', 'critica', IncidentStatus::Blocked],
            ['Responsable incorrecto en el informe', 'Se ha corregido la asignación mostrada en el informe semanal.', 'media', IncidentStatus::Resolved],
            ['El filtro de fechas devuelve resultados incompletos', 'Faltan registros del último día del intervalo seleccionado.', 'alta', IncidentStatus::Open],
            ['Sesión interrumpida durante la consulta', 'Se está investigando una desconexión al abrir un expediente.', 'media', IncidentStatus::InReview],
            ['Acceso pendiente a la carpeta compartida', 'Se necesita autorización del departamento para continuar.', 'baja', IncidentStatus::Blocked],
        ];

        foreach ($examples as [$title, $description, $priority, $status]) {
            DB::transaction(function () use ($service, $title, $description, $priority, $status, $ana, $luis) {
                if (Incident::where('title', $title)->where('requester_name', 'Ana (demo)')->exists()) {
                    return;
                }

                $incident = $service->create([
                    'title' => $title,
                    'description' => $description,
                    'priority' => $priority,
                    'requester_name' => 'Ana (demo)',
                    'assigned_to' => 'Luis (demo)',
                ], $ana);

                if ($status !== IncidentStatus::Open) {
                    $incident = $service->changeStatus($incident, IncidentStatus::InReview, $luis);
                }

                if (in_array($status, [IncidentStatus::Blocked, IncidentStatus::Resolved], true)) {
                    $incident = $service->changeStatus($incident, $status, $luis);
                }

                $service->addComment($incident, $luis, $description);
            });
        }
    }
}
