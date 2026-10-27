<?php

namespace Tests\Feature;

use App\Enums\IncidentAction;
use App\Enums\IncidentStatus;
use App\Livewire\IncidentDetail;
use App\Models\Incident;
use App\Models\IncidentLog;
use App\Models\User;
use App\Services\IncidentAuditService;
use App\Services\IncidentService;
use Database\Seeders\IncidentSeeder;
use Database\Seeders\InconsistentIncidentSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class IncidentAuditTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function reviewIncident(): Incident
    {
        $service = app(IncidentService::class);

        return $service->changeStatus($service->create(Incident::factory()->raw()), IncidentStatus::InReview, 'Luis');
    }

    private function duplicate(Incident $incident, array $overrides = []): int
    {
        $data = (array) DB::table('incident_logs')->where('incident_id', $incident->id)->orderByDesc('id')->first();
        unset($data['id']);

        return DB::table('incident_logs')->insertGetId(array_replace($data, $overrides));
    }

    public function test_consistent_data_is_clean_even_with_comments_after_resolution_and_reopening(): void
    {
        $this->seed(IncidentSeeder::class);
        $incident = Incident::where('status', 'resuelta')->firstOrFail();
        $service = app(IncidentService::class);
        $incident = $service->changeStatus($incident, IncidentStatus::Open, 'Ana', 'El problema persiste');
        $service->addComment($incident, 'Ana', 'Revisar de nuevo');

        $this->artisan('incidents:audit')->expectsOutputToContain('Sin inconsistencias pendientes')->assertExitCode(0);
    }

    public function test_seeder_is_repeatable_and_dry_run_detects_all_three_cases_without_any_writes(): void
    {
        $this->seed(InconsistentIncidentSeeder::class);
        $this->seed(InconsistentIncidentSeeder::class);
        $before = DB::table('incident_logs')->orderBy('id')->get()->toJson();
        $incidents = DB::table('incidents')->orderBy('id')->get()->toJson();

        $this->artisan('incidents:audit', ['--dry-run' => true])
            ->expectsOutputToContain('DRY-RUN')->expectsOutputToContain('resolved_without_log')
            ->expectsOutputToContain('status_mismatch')->expectsOutputToContain('duplicate_log')->assertExitCode(1);

        $this->assertDatabaseCount('incidents', 3);
        $this->assertSame($before, DB::table('incident_logs')->orderBy('id')->get()->toJson());
        $this->assertSame($incidents, DB::table('incidents')->orderBy('id')->get()->toJson());
    }

    public function test_fix_marks_exact_duplicate_preserves_evidence_and_is_idempotent(): void
    {
        $incident = $this->reviewIncident();
        $canonical = $incident->logs()->get()->last();
        $duplicateId = $this->duplicate($incident);
        $before = (array) DB::table('incident_logs')->find($duplicateId);

        $this->artisan('incidents:audit', ['--fix' => true, '--actor' => 'Marta'])
            ->expectsOutputToContain('Duplicados marcados: 1')->assertExitCode(0);

        $after = (array) DB::table('incident_logs')->find($duplicateId);
        foreach (['action', 'old_value', 'new_value', 'metadata', 'user_name', 'created_at'] as $field) {
            $this->assertSame($before[$field], $after[$field]);
        }
        $this->assertSame($canonical->id, $after['duplicate_of_id']);
        $this->assertNotNull($after['voided_at']);
        $auditLog = IncidentLog::where('action', 'duplicate_marked')->firstOrFail();
        $this->assertSame('Marta', $auditLog->user_name);
        $this->assertSame($duplicateId, $auditLog->metadata['duplicate_log_id']);
        $this->assertSame($canonical->id, $auditLog->metadata['canonical_log_id']);
        $this->assertSame($before['created_at'], $auditLog->metadata['original']['created_at']);
        $this->assertDatabaseCount('incident_logs', 4);
        $this->artisan('incidents:audit', ['--fix' => true])->expectsOutputToContain('Duplicados marcados: 0')->assertExitCode(0);
        $this->assertDatabaseCount('incident_logs', 4);
        $this->assertSame(IncidentStatus::InReview, $incident->fresh()->status);
        Livewire::actingAs(User::factory()->create())->test(IncidentDetail::class, ['incidentId' => $incident->id])
            ->assertSee('Duplicado del evento')->assertSee('Duplicado marcado por auditoría');
    }

    public function test_ambiguous_states_are_never_changed_or_given_invented_logs(): void
    {
        $this->seed(InconsistentIncidentSeeder::class);
        $before = DB::table('incidents')->orderBy('id')->get()->toJson();
        $this->artisan('incidents:audit', ['--fix' => true])->expectsOutputToContain('Duplicados marcados: 1')
            ->expectsOutputToContain('Revisión manual')->assertExitCode(1);
        $this->assertSame($before, DB::table('incidents')->orderBy('id')->get()->toJson());
        $this->assertDatabaseMissing('incident_logs', ['new_value' => 'resuelta']);
        $this->assertDatabaseMissing('incident_logs', ['new_value' => 'bloqueada']);
        $this->seed(InconsistentIncidentSeeder::class);
        $this->assertDatabaseCount('incidents', 3);
        $this->assertDatabaseCount('incident_logs', 8);
    }

    public function test_repair_and_audit_event_roll_back_together_on_failure(): void
    {
        $incident = $this->reviewIncident();
        $duplicate = $this->duplicate($incident);
        IncidentLog::creating(function (IncidentLog $log): void {
            if ($log->action === IncidentAction::DuplicateMarked) {
                throw new RuntimeException('Audit unavailable');
            }
        });
        try {
            app(IncidentAuditService::class)->run(fix: true);
            $this->fail('Expected failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        } finally {
            IncidentLog::flushEventListeners();
        }
        $this->assertDatabaseHas('incident_logs', ['id' => $duplicate, 'duplicate_of_id' => null, 'voided_at' => null]);
        $this->assertDatabaseCount('incident_logs', 3);
    }

    public function test_invalid_values_and_broken_chains_are_reported_without_enum_cast_failures(): void
    {
        $incident = $this->reviewIncident();
        DB::table('incidents')->where('id', $incident->id)->update(['status' => 'desconocido']);
        $this->duplicate($incident, ['action' => 'imported_unknown', 'new_value' => 'otro']);
        $codes = array_column(app(IncidentAuditService::class)->run(fix: true)['findings'], 'code');
        $this->assertContains('invalid_status', $codes);
        $this->assertContains('invalid_log', $codes);
        $this->assertContains('status_mismatch', $codes);
        $this->assertDatabaseCount('incident_logs', 3);
    }

    public function test_repeated_transition_after_a_real_cycle_is_not_a_duplicate(): void
    {
        $this->freezeTime();
        $service = app(IncidentService::class);
        $incident = $this->reviewIncident();
        foreach ([IncidentStatus::Blocked, IncidentStatus::InReview, IncidentStatus::Blocked] as $target) {
            $incident = $service->changeStatus($incident, $target, 'Luis');
        }
        $this->assertSame([], app(IncidentAuditService::class)->run()['findings']);
    }

    public function test_near_duplicates_with_different_actor_or_metadata_require_manual_review(): void
    {
        $incident = $this->reviewIncident();
        $this->duplicate($incident, ['user_name' => 'Ana']);
        $this->duplicate($incident, ['metadata' => json_encode(['source' => 'import'])]);
        $result = app(IncidentAuditService::class)->run(fix: true);
        $this->assertSame(0, $result['repaired']);
        $this->assertContains('broken_chain', array_column($result['findings'], 'code'));
        $this->assertNotContains('duplicate_log', array_column($result['findings'], 'code'));
    }

    public function test_multiple_identical_copies_are_all_linked_to_the_first_event(): void
    {
        $incident = $this->reviewIncident();
        $originalId = $incident->logs()->get()->last()->id;
        $this->duplicate($incident);
        $this->duplicate($incident);
        $result = app(IncidentAuditService::class)->run(fix: true);
        $this->assertSame(2, $result['repaired']);
        $this->assertSame([], $result['findings']);
        $this->assertSame(2, DB::table('incident_logs')->where('duplicate_of_id', $originalId)->count());
    }

    public function test_scope_and_invalid_options_cannot_trigger_unintended_repairs(): void
    {
        $first = $this->reviewIncident();
        $second = $this->reviewIncident();
        $this->duplicate($first);
        $secondDuplicate = $this->duplicate($second);
        $this->artisan('incidents:audit', ['--fix' => true, '--dry-run' => true])->assertExitCode(2);
        $this->artisan('incidents:audit', ['--fix' => true, '--incident' => 'no'])->assertExitCode(2);
        $this->artisan('incidents:audit', ['--fix' => true, '--incident' => '999999'])->assertExitCode(2);
        $this->artisan('incidents:audit', ['--fix' => true, '--actor' => '   '])->assertExitCode(2);
        $this->assertDatabaseCount('incident_logs', 6);
        $this->artisan('incidents:audit', ['--fix' => true, '--incident' => (string) $first->id])->assertExitCode(0);
        $this->assertDatabaseHas('incident_logs', ['id' => $secondDuplicate, 'duplicate_of_id' => null]);
    }

    public function test_similar_events_at_different_times_are_not_automatically_marked(): void
    {
        $this->freezeTime();
        $incident = $this->reviewIncident();
        $this->travel(1)->seconds();
        $copy = $this->duplicate($incident, ['created_at' => now()]);
        $result = app(IncidentAuditService::class)->run(fix: true);

        $this->assertSame(0, $result['repaired']);
        $this->assertContains('broken_chain', array_column($result['findings'], 'code'));
        $this->assertDatabaseHas('incident_logs', ['id' => $copy, 'duplicate_of_id' => null]);
    }

    public function test_unknown_history_and_invalid_duplicate_markers_require_manual_review(): void
    {
        $withoutHistory = Incident::factory()->create();
        $incident = $this->reviewIncident();
        $copy = $this->duplicate($incident, ['duplicate_of_id' => $incident->logs()->first()->id, 'voided_at' => now()]);
        $result = app(IncidentAuditService::class)->run(fix: true);

        $this->assertSame(0, $result['repaired']);
        $this->assertContains('missing_history', array_column($result['findings'], 'code'));
        $this->assertContains('invalid_duplicate_marker', array_column($result['findings'], 'code'));
        $this->assertDatabaseHas('incidents', ['id' => $withoutHistory->id, 'status' => 'abierta']);
        $this->assertDatabaseCount('incident_logs', 3);
    }

    public function test_reopening_without_explicit_action_or_reason_is_incoherent(): void
    {
        $service = app(IncidentService::class);
        $incident = $service->changeStatus($this->reviewIncident(), IncidentStatus::Resolved, 'Luis');
        $this->duplicate($incident, ['action' => 'reopened', 'old_value' => 'resuelta', 'new_value' => 'abierta', 'metadata' => null]);
        DB::table('incidents')->where('id', $incident->id)->update(['status' => 'abierta']);

        $result = app(IncidentAuditService::class)->run(fix: true);
        $this->assertSame(0, $result['repaired']);
        $this->assertContains('invalid_log', array_column($result['findings'], 'code'));
    }
}
