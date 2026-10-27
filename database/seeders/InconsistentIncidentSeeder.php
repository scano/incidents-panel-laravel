<?php

namespace Database\Seeders;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\User;
use App\Services\IncidentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InconsistentIncidentSeeder extends Seeder
{
    public function run(IncidentService $service): void
    {
        $this->call(UserSeeder::class);
        $actor = User::where('email', 'luis@example.test')->firstOrFail();

        foreach (['resolved_without_log', 'status_mismatch', 'duplicate_log'] as $case) {
            DB::transaction(function () use ($service, $case, $actor) {
                $title = '[Auditoría demo] '.$case;
                if (Incident::where('title', $title)->where('requester_name', 'Mantenimiento (demo)')->exists()) {
                    return;
                }

                $incident = $service->create([
                    'title' => $title,
                    'description' => 'Caso deliberadamente inconsistente para ejecutar incidents:audit. No representa una operación real.',
                    'priority' => 'media',
                    'requester_name' => 'Mantenimiento (demo)',
                ], $actor);
                $incident = $service->changeStatus($incident, IncidentStatus::InReview, $actor);

                if ($case === 'duplicate_log') {
                    $original = (array) DB::table('incident_logs')->where('incident_id', $incident->id)->orderByDesc('id')->first();
                    unset($original['id']);
                    DB::table('incident_logs')->insert($original);
                } else {
                    DB::table('incidents')->where('id', $incident->id)->update([
                        'status' => $case === 'resolved_without_log' ? 'resuelta' : 'bloqueada',
                    ]);
                }
            });
        }
    }
}
