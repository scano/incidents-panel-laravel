<?php

namespace Tests\Feature;

use App\Enums\IncidentStatus;
use App\Livewire\CreateIncident;
use App\Livewire\IncidentDashboard;
use App\Livewire\IncidentDetail;
use App\Models\Incident;
use App\Models\User;
use App\Services\IncidentService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IncidentPanelTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['name' => 'Luis']));
    }

    private function incident(array $input = []): Incident
    {
        return app(IncidentService::class)->create(Incident::factory()->raw($input));
    }

    public function test_filters_are_combined_and_search_does_not_bypass_them(): void
    {
        $service = app(IncidentService::class);
        $matching = $service->changeStatus($this->incident(['title' => 'Exportación pendiente', 'priority' => 'alta']), IncidentStatus::InReview, 'Luis');
        $wrongStatus = $this->incident(['title' => 'Exportación abierta', 'priority' => 'alta']);
        $wrongPriority = $service->changeStatus($this->incident(['title' => 'Exportación baja', 'priority' => 'baja']), IncidentStatus::InReview, 'Luis');
        $wrongText = $service->changeStatus($this->incident(['title' => 'Acceso al sistema', 'description' => 'Sin acceso', 'priority' => 'alta']), IncidentStatus::InReview, 'Luis');

        Livewire::test(IncidentDashboard::class)
            ->set('status', 'en_revision')->set('priority', 'alta')->set('search', 'Exportación')
            ->assertViewHas('incidents', fn ($incidents) => $incidents->modelKeys() === [$matching->id])
            ->assertDontSee($wrongStatus->title)->assertDontSee($wrongPriority->title)->assertDontSee($wrongText->title)
            ->assertViewHas('counts', fn ($counts) => $counts['abierta'] === 1 && $counts['en_revision'] === 3)
            ->set('search', "' OR 1=1 --")->assertViewHas('incidents', fn ($incidents) => $incidents->isEmpty())
            ->call('clearFilters')->assertSet('status', '')->assertSet('priority', '')->assertSet('search', '')
            ->assertViewHas('incidents', fn ($incidents) => $incidents->total() === 4);
    }

    public function test_filtering_resets_pagination_and_url_filters_are_supported(): void
    {
        Incident::factory()->count(12)->create(['status' => 'abierta']);

        Livewire::withQueryParams(['status' => 'abierta'])->test(IncidentDashboard::class)
            ->assertSet('status', 'abierta')->assertSee('Mostrando 1 a 10 de 12 incidencias')
            ->assertSee('Anterior')->assertSee('Siguiente')->assertDontSee('Showing')
            ->call('nextPage')->assertViewHas('incidents', fn ($incidents) => $incidents->currentPage() === 2)
            ->assertSee('Mostrando 11 a 12 de 12 incidencias')
            ->set('priority', 'media')->assertViewHas('incidents', fn ($incidents) => $incidents->currentPage() === 1);
    }

    public function test_create_form_validates_and_dispatches_only_after_persisting(): void
    {
        $component = Livewire::test(CreateIncident::class)
            ->call('save')->assertHasErrors(['title', 'description', 'requester_name'])
            ->assertNotDispatched('incident-created');
        $this->assertDatabaseCount('incidents', 0);

        $component->set('title', 'Error de descarga')->set('description', 'El informe no se descarga')
            ->set('requester_name', 'Ana')->set('priority', 'alta')->call('save')
            ->assertHasNoErrors()->assertDispatched('incident-created');
        $this->assertDatabaseHas('incidents', ['title' => 'Error de descarga', 'status' => 'abierta']);
        $this->assertDatabaseHas('incident_logs', ['action' => 'created', 'user_name' => 'Luis', 'user_id' => auth()->id()]);
    }

    public function test_creation_event_closes_form_refreshes_list_and_keeps_detail_closed(): void
    {
        $incident = $this->incident();
        Livewire::test(IncidentDashboard::class)->set('creating', true)->set('search', 'inexistente')
            ->dispatch('incident-created', id: $incident->id)
            ->assertSet('creating', false)->assertSet('search', '')->assertSet('selectedId', null)
            ->assertSee([$incident->title, 'Incidencia creada correctamente.']);
    }

    public function test_detail_changes_status_through_service_and_updates_history(): void
    {
        $incident = $this->incident();
        Livewire::test(IncidentDetail::class, ['incidentId' => $incident->id])
            ->set('target', 'en_revision')->call('changeStatus')
            ->assertHasNoErrors()->assertSet('observedStatus', 'en_revision')->assertDispatched('incident-updated')
            ->assertSee('Estado actualizado');
        $this->assertDatabaseHas('incidents', ['id' => $incident->id, 'status' => 'en_revision']);
        $this->assertDatabaseHas('incident_logs', ['incident_id' => $incident->id, 'old_value' => 'abierta', 'new_value' => 'en_revision', 'user_name' => 'Luis']);
    }

    public function test_detail_preserves_observed_status_until_explicit_refresh(): void
    {
        $incident = $this->incident();
        $component = Livewire::test(IncidentDetail::class, ['incidentId' => $incident->id]);
        app(IncidentService::class)->changeStatus($incident, IncidentStatus::InReview, 'Luis');

        $component->set('target', 'en_revision')->call('changeStatus')->assertHasErrors('status')
            ->assertSee('La incidencia ha cambiado')->assertSet('observedStatus', 'abierta')
            ->assertNotDispatched('incident-updated')
            ->call('refreshIncident')->assertSet('observedStatus', 'en_revision')->assertHasNoErrors();
        $this->assertDatabaseCount('incident_logs', 2);
    }

    public function test_detail_rejects_invalid_transitions_and_requires_reopening_reason(): void
    {
        $incident = $this->incident();
        $component = Livewire::test(IncidentDetail::class, ['incidentId' => $incident->id]);
        $component->set('target', 'resuelta')->call('changeStatus')->assertHasErrors('status');
        $component->set('target', 'inventado')->call('changeStatus')->assertHasErrors('target');
        $this->assertSame(IncidentStatus::Open, $incident->fresh()->status);

        $service = app(IncidentService::class);
        $incident = $service->changeStatus($incident, IncidentStatus::InReview, 'Luis');
        $service->changeStatus($incident, IncidentStatus::Resolved, 'Luis');
        $component->call('refreshIncident')->set('target', 'abierta')->call('changeStatus')->assertHasErrors('reason')
            ->set('reason', 'El fallo sigue activo')->call('changeStatus')->assertHasNoErrors()->assertSee('Incidencia reabierta');
        $this->assertDatabaseHas('incident_logs', ['incident_id' => $incident->id, 'action' => 'reopened']);
    }

    public function test_comments_are_validated_persisted_and_escaped_in_detail(): void
    {
        $incident = $this->incident(['title' => '<script>alert(1)</script>']);
        Livewire::test(IncidentDetail::class, ['incidentId' => $incident->id])
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->call('addComment')->assertHasErrors(['body'])
            ->assertSet('notice', '')
            ->set('body', '<img src=x onerror=alert(1)>')->call('addComment')
            ->assertHasNoErrors()->assertSet('body', '')->assertSet('notice', '')->assertDispatched('incident-updated')
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)->assertDontSee('<img src=x onerror=alert(1)>', false);
        $this->assertDatabaseHas('incident_comments', ['incident_id' => $incident->id, 'author_name' => 'Luis', 'user_id' => auth()->id(), 'body' => '<img src=x onerror=alert(1)>']);
        $this->assertDatabaseHas('incident_logs', ['incident_id' => $incident->id, 'action' => 'comment_added']);
    }

    public function test_comments_show_newest_first_and_a_new_comment_is_prepended(): void
    {
        $this->freezeTime();
        $incident = $this->incident();
        $service = app(IncidentService::class);
        $oldest = $service->addComment($incident, auth()->user(), 'Primer comentario');
        $this->travel(10)->seconds();
        $second = $service->addComment($incident, auth()->user(), 'Segundo comentario');
        $third = $service->addComment($incident, auth()->user(), 'Tercer comentario en el mismo segundo');

        Livewire::test(IncidentDetail::class, ['incidentId' => $incident->id])
            ->assertViewHas('incident', fn (Incident $rendered) => $rendered->comments->modelKeys() === [$third->id, $second->id, $oldest->id])
            ->set('body', 'Comentario más reciente')->call('addComment')->assertHasNoErrors()
            ->assertDispatched('comment-added')->assertSet('body', '')
            ->assertViewHas('incident', fn (Incident $rendered) => $rendered->comments->pluck('body')->all() === [
                'Comentario más reciente', 'Tercer comentario en el mismo segundo', 'Segundo comentario', 'Primer comentario',
            ]);
    }

    public static function lockedProperties(): array
    {
        return ['incident identifier' => ['incidentId', 999], 'observed status' => ['observedStatus', 'resuelta']];
    }

    #[DataProvider('lockedProperties')]
    public function test_client_cannot_change_detail_identity_or_observed_status(string $property, mixed $value): void
    {
        $incident = $this->incident();
        $component = Livewire::test(IncidentDetail::class, ['incidentId' => $incident->id]);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $component->set($property, $value);
    }
}
