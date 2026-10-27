<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\User;
use App\Services\IncidentService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InfrastructureCleanupTest extends TestCase
{
    use DatabaseMigrations;

    public function test_migration_can_be_reversed_and_reapplied_without_losing_application_data(): void
    {
        $user = User::factory()->create();
        $service = app(IncidentService::class);
        $incident = $service->create(Incident::factory()->raw(['title' => 'Incidencia conservada']), $user);
        $comment = $service->addComment($incident, $user, 'Seguimiento conservado');
        $removed = ['jobs', 'failed_jobs', 'job_batches', 'password_reset_tokens'];
        foreach ($removed as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        foreach (['cache', 'cache_locks', 'sessions', 'migrations'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertExitCode(0);
        foreach ($removed as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        foreach ($removed as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
        $this->assertDatabaseHas('incidents', ['id' => $incident->id, 'title' => 'Incidencia conservada']);
        $this->assertDatabaseHas('incident_comments', ['id' => $comment->id, 'body' => 'Seguimiento conservado', 'user_id' => $user->id]);
        $this->assertDatabaseHas('incident_logs', ['incident_id' => $incident->id, 'action' => 'created', 'user_id' => $user->id]);
        $this->assertDatabaseHas('incident_logs', ['incident_id' => $incident->id, 'action' => 'comment_added', 'user_id' => $user->id]);
    }
}
