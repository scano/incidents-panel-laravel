<?php

namespace Tests\Feature;

use App\Enums\IncidentAction;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\IncidentLog;
use App\Models\User;
use App\Services\IncidentService;
use Database\Seeders\IncidentSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class IncidentServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function createIncident(array $overrides = []): Incident
    {
        return app(IncidentService::class)->create(Incident::factory()->raw(array_replace([
            'title' => 'Error al exportar',
            'description' => 'La descarga del informe devuelve un error.',
            'priority' => 'alta',
            'requester_name' => 'Ana',
        ], $overrides)));
    }

    public function test_creation_always_starts_open_and_records_the_requester(): void
    {
        $incident = $this->createIncident(['title' => '  Exportación  ', 'status' => 'resuelta']);

        $this->assertSame('Exportación', $incident->title);
        $this->assertSame(IncidentStatus::Open, $incident->status);
        $this->assertNull($incident->assigned_to);
        $this->assertDatabaseHas('incident_logs', [
            'incident_id' => $incident->id,
            'action' => 'created',
            'old_value' => null,
            'new_value' => 'abierta',
            'user_name' => 'Ana',
        ]);
        $this->assertNotNull($incident->logs()->first()->created_at);
    }

    public function test_creation_validates_before_writing_anything(): void
    {
        try {
            $this->createIncident(['title' => '   ', 'priority' => 'urgente']);
            $this->fail('Expected validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('title', $exception->errors());
            $this->assertArrayHasKey('priority', $exception->errors());
        }

        $this->assertDatabaseCount('incidents', 0);
        $this->assertDatabaseCount('incident_logs', 0);
    }

    public function test_blocking_unblocking_and_resolution_record_a_complete_chain(): void
    {
        $service = app(IncidentService::class);
        $incident = $this->createIncident();
        foreach ([IncidentStatus::InReview, IncidentStatus::Blocked, IncidentStatus::InReview, IncidentStatus::Resolved] as $target) {
            $incident = $service->changeStatus($incident, $target, 'Luis');
        }

        $this->assertSame(IncidentStatus::Resolved, $incident->fresh()->status);
        $this->assertSame([
            [null, 'abierta'],
            ['abierta', 'en_revision'],
            ['en_revision', 'bloqueada'],
            ['bloqueada', 'en_revision'],
            ['en_revision', 'resuelta'],
        ], $incident->logs->map(fn ($log) => [$log->old_value, $log->new_value])->all());
        $this->assertSame('Luis', $incident->logs->last()->user_name);
    }

    public static function forbiddenTransitions(): array
    {
        return [
            ['abierta', 'abierta'], ['abierta', 'bloqueada'], ['abierta', 'resuelta'],
            ['en_revision', 'abierta'], ['en_revision', 'en_revision'],
            ['bloqueada', 'abierta'], ['bloqueada', 'bloqueada'], ['bloqueada', 'resuelta'],
            ['resuelta', 'en_revision'], ['resuelta', 'bloqueada'], ['resuelta', 'resuelta'],
        ];
    }

    #[DataProvider('forbiddenTransitions')]
    public function test_forbidden_transitions_leave_no_changes(string $from, string $to): void
    {
        $incident = $this->createIncident();
        // Direct writes here deliberately set up each starting condition.
        $incident->update(['status' => $from]);

        try {
            app(IncidentService::class)->changeStatus($incident, IncidentStatus::from($to), 'Luis');
            $this->fail('Expected invalid transition.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame($from, $incident->fresh()->status->value);
        $this->assertDatabaseCount('incident_logs', 1);
    }

    public function test_reopening_requires_a_reason_and_has_a_distinct_event(): void
    {
        $service = app(IncidentService::class);
        $incident = $service->changeStatus($this->createIncident(), IncidentStatus::InReview, 'Luis');
        $incident = $service->changeStatus($incident, IncidentStatus::Resolved, 'Luis');

        try {
            $service->changeStatus($incident, IncidentStatus::Open, 'Ana', '   ');
            $this->fail('Expected missing reason.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }
        $this->assertSame(IncidentStatus::Resolved, $incident->fresh()->status);
        $this->assertDatabaseCount('incident_logs', 3);

        $incident = $service->changeStatus($incident, IncidentStatus::Open, 'Ana', '  El fallo persiste  ');
        $log = $incident->logs->last();
        $this->assertSame(IncidentAction::Reopened, $log->action);
        $this->assertSame('resuelta', $log->old_value);
        $this->assertSame('abierta', $log->new_value);
        $this->assertSame(['reason' => 'El fallo persiste'], $log->metadata);
    }

    public function test_a_stale_model_cannot_overwrite_a_newer_status(): void
    {
        $service = app(IncidentService::class);
        $stale = $this->createIncident();
        $service->changeStatus($stale, IncidentStatus::InReview, 'Luis');

        try {
            $service->changeStatus($stale, IncidentStatus::InReview, 'Ana');
            $this->fail('Expected stale status rejection.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('ha cambiado', $exception->errors()['status'][0]);
        }

        $this->assertSame(IncidentStatus::InReview, $stale->fresh()->status);
        $this->assertDatabaseCount('incident_logs', 2);
    }

    public function test_blank_actor_is_rejected_without_changing_status(): void
    {
        $incident = $this->createIncident();
        try {
            app(IncidentService::class)->changeStatus($incident, IncidentStatus::InReview, '   ');
            $this->fail('Expected missing actor.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user_name', $exception->errors());
        }
        $this->assertSame(IncidentStatus::Open, $incident->fresh()->status);
        $this->assertDatabaseCount('incident_logs', 1);
    }

    public static function operations(): array
    {
        return [['create'], ['changeStatus'], ['addComment']];
    }

    #[DataProvider('operations')]
    public function test_log_failure_rolls_back_the_entire_operation(string $operation): void
    {
        $service = app(IncidentService::class);
        $incident = $this->createIncident();
        IncidentLog::creating(fn () => throw new RuntimeException('Log unavailable'));

        try {
            match ($operation) {
                'create' => $this->createIncident(),
                'changeStatus' => $service->changeStatus($incident, IncidentStatus::InReview, 'Luis'),
                'addComment' => $service->addComment($incident, 'Luis', 'Revisando'),
            };
            $this->fail('Expected log failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Log unavailable', $exception->getMessage());
        } finally {
            IncidentLog::flushEventListeners();
        }

        $this->assertDatabaseCount('incidents', 1);
        $this->assertDatabaseCount('incident_logs', 1);
        $this->assertDatabaseCount('incident_comments', 0);
        $this->assertSame(IncidentStatus::Open, $incident->fresh()->status);
    }

    public function test_comments_are_validated_linked_and_traced(): void
    {
        $incident = $this->createIncident();
        $service = app(IncidentService::class);
        try {
            $service->addComment($incident, 'Luis', '   ');
            $this->fail('Expected missing body.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('body', $exception->errors());
        }
        $this->assertDatabaseCount('incident_comments', 0);

        $comment = $service->addComment($incident, ' Luis ', ' Revisando los registros ');
        $this->assertSame($incident->id, $comment->incident->id);
        $this->assertSame('Luis', $comment->author_name);
        $this->assertSame('Revisando los registros', $comment->body);
        $this->assertSame($comment->id, $incident->comments->first()->id);
        $log = $incident->logs->last();
        $this->assertSame(IncidentAction::CommentAdded, $log->action);
        $this->assertSame(['comment_id' => $comment->id], $log->metadata);
        $this->assertNull($log->new_value);
    }

    public function test_demo_seeder_creates_valid_histories_and_can_be_run_again(): void
    {
        $this->seed(IncidentSeeder::class);
        $this->seed(IncidentSeeder::class);

        $this->assertDatabaseCount('incidents', 15);
        $this->assertDatabaseCount('incident_comments', 15);
        $this->assertDatabaseCount('incident_logs', 48);
        $ana = User::where('email', 'ana@example.test')->firstOrFail();
        $luis = User::where('email', 'luis@example.test')->firstOrFail();
        $this->assertDatabaseMissing('incident_logs', ['user_id' => null]);
        $this->assertDatabaseMissing('incident_comments', ['user_id' => null]);
        $this->assertSame(15, IncidentLog::where('action', 'created')->where('user_id', $ana->id)->count());
        $this->assertSame(15, IncidentComment::where('user_id', $luis->id)->count());

        foreach (Incident::with('logs')->get() as $incident) {
            $statusLogs = $incident->logs->whereIn('action', [IncidentAction::Created, IncidentAction::StatusChanged]);
            $this->assertSame($incident->status->value, $statusLogs->last()->new_value);
        }
    }
}
