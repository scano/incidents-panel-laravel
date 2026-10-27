<?php

namespace Tests\Feature;

use App\Enums\IncidentStatus;
use App\Livewire\CreateIncident;
use App\Livewire\IncidentDashboard;
use App\Livewire\IncidentDetail;
use App\Models\Incident;
use App\Models\User;
use App\Services\IncidentAuditService;
use App\Services\IncidentService;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_are_redirected_to_login_and_can_view_the_form(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Correo electrónico')->assertSee('Contraseña');
        $this->get('/register')->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
        $this->get('/logout')->assertMethodNotAllowed();
    }

    public function test_valid_login_normalizes_email_authenticates_and_regenerates_the_session(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.test']);
        $this->get(route('login'));
        $before = session()->getId();

        $this->post(route('login.store'), ['email' => ' ANA@EXAMPLE.TEST ', 'password' => 'Incidencias123!'])
            ->assertSessionHasNoErrors()->assertRedirect(route('incidents.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
        $this->get(route('login'))->assertRedirect(route('incidents.index'));
    }

    public function test_invalid_credentials_do_not_log_in_or_flash_the_password(): void
    {
        $user = User::factory()->create();
        $this->from(route('login'))->post(route('login.store'), ['email' => $user->email, 'password' => 'incorrecta'])
            ->assertRedirect(route('login'))->assertSessionHasErrors(['email' => 'El correo o la contraseña no son correctos.'])
            ->assertSessionMissing('_old_input.password');
        $this->assertGuest();
    }

    public function test_login_validates_missing_and_malformed_inputs(): void
    {
        $this->post(route('login.store'), [])->assertSessionHasErrors(['email', 'password']);
        $this->post(route('login.store'), ['email' => 'no-es-correo', 'password' => 'incorrecta'])
            ->assertSessionHasErrors(['email' => 'Introduce una dirección de correo válida.']);
        $this->assertGuest();
    }

    public function test_failed_logins_are_limited_and_can_retry_after_the_cooldown(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'incorrecta'])->assertSessionHasErrors('email');
        }
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Incidencias123!'])
            ->assertSessionHasErrors(['email' => 'Demasiados intentos. Vuelve a intentarlo en 60 segundos.']);
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Incidencias123!'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_invalidates_the_session_and_protects_the_panel_again(): void
    {
        $this->actingAs(User::factory()->create())->withSession(['private_marker' => 'value']);
        $beforeToken = session()->token();
        $this->post(route('logout'))->assertRedirect(route('login'))->assertSessionMissing('private_marker');
        $this->assertGuest();
        $this->assertNotSame($beforeToken, session()->token());
        $this->get('/')->assertRedirect(route('login'));
    }

    public static function components(): array
    {
        return ['list' => [IncidentDashboard::class], 'create' => [CreateIncident::class], 'detail' => [IncidentDetail::class]];
    }

    #[DataProvider('components')]
    public function test_guests_cannot_mount_livewire_components_directly(string $component): void
    {
        $incident = Incident::factory()->create();
        Livewire::test($component, $component === IncidentDetail::class ? ['incidentId' => $incident->id] : [])->assertForbidden();
        $this->assertDatabaseCount('incident_logs', 0);
    }

    public function test_expired_session_cannot_submit_a_comment(): void
    {
        $incident = Incident::factory()->create();
        $component = Livewire::actingAs(User::factory()->create())->test(IncidentDetail::class, ['incidentId' => $incident->id])
            ->set('body', 'Comentario sin sesión');
        Auth::logout();
        $component->call('addComment')->assertForbidden();
        $this->assertDatabaseCount('incident_comments', 0);
        $this->assertDatabaseCount('incident_logs', 0);
    }

    public function test_creation_records_logged_in_actor_and_keeps_requester_separate(): void
    {
        $user = User::factory()->create(['name' => 'Luis']);
        Livewire::actingAs($user)->test(CreateIncident::class)
            ->set('title', 'Incidencia registrada por otra persona')->set('description', 'Descripción de la incidencia')
            ->set('requester_name', 'Solicitante externo')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('incidents', ['requester_name' => 'Solicitante externo']);
        $this->assertDatabaseHas('incident_logs', ['action' => 'created', 'user_id' => $user->id, 'user_name' => 'Luis']);
    }

    public function test_status_and_comments_use_session_identity_and_preserve_name_snapshots(): void
    {
        $user = User::factory()->create(['name' => 'Ana']);
        $incident = app(IncidentService::class)->create(Incident::factory()->raw(['requester_name' => 'Actor histórico']));
        $component = Livewire::actingAs($user)->test(IncidentDetail::class, ['incidentId' => $incident->id])
            ->assertDontSee('Tu nombre *')->assertDontSee('Autor del comentario *')
            ->set('target', 'en_revision')->call('changeStatus')->assertHasNoErrors()
            ->set('body', 'Comentario identificado')->call('addComment')->assertHasNoErrors();

        $this->assertDatabaseHas('incident_logs', ['action' => 'created', 'user_name' => 'Actor histórico', 'user_id' => null]);
        $this->assertDatabaseHas('incident_logs', ['action' => 'status_changed', 'user_name' => 'Ana', 'user_id' => $user->id]);
        $this->assertDatabaseHas('incident_logs', ['action' => 'comment_added', 'user_name' => 'Ana', 'user_id' => $user->id]);
        $this->assertDatabaseHas('incident_comments', ['author_name' => 'Ana', 'user_id' => $user->id, 'body' => 'Comentario identificado']);
        $user->update(['name' => 'Ana actualizada']);
        $this->assertSame('Ana', $incident->comments()->first()->author_name);
        $this->assertSame('Ana', $incident->logs()->where('action', 'status_changed')->first()->user_name);
        $this->assertSame($user->id, $incident->comments()->first()->user->id);
    }

    public function test_different_accounts_with_the_same_name_are_not_treated_as_duplicate_actors(): void
    {
        $first = User::factory()->create(['name' => 'Ana']);
        $second = User::factory()->create(['name' => 'Ana']);
        $service = app(IncidentService::class);
        $incident = $service->changeStatus($service->create(Incident::factory()->raw(), $first), IncidentStatus::InReview, $first);
        $copy = (array) DB::table('incident_logs')->where('incident_id', $incident->id)->orderByDesc('id')->first();
        unset($copy['id']);
        DB::table('incident_logs')->insert([...$copy, 'user_id' => $second->id]);

        $result = app(IncidentAuditService::class)->run(fix: true);
        $this->assertSame(0, $result['repaired']);
        $this->assertContains('broken_chain', array_column($result['findings'], 'code'));
    }

    public function test_user_seeder_is_repeatable_without_resetting_existing_passwords(): void
    {
        $this->seed(UserSeeder::class);
        $ana = User::where('email', 'ana@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Incidencias123!', $ana->password));
        $ana->update(['password' => 'ClaveCambiada123!']);
        $this->seed(UserSeeder::class);
        $this->assertDatabaseCount('users', 2);
        $this->assertTrue(Hash::check('ClaveCambiada123!', $ana->fresh()->password));
    }

    public static function forgedIdentityFields(): array
    {
        return ['status actor' => ['actor'], 'comment author' => ['author_name']];
    }

    #[DataProvider('forgedIdentityFields')]
    public function test_client_cannot_inject_a_forged_identity(string $property): void
    {
        $incident = Incident::factory()->create();
        $component = Livewire::actingAs(User::factory()->create())->test(IncidentDetail::class, ['incidentId' => $incident->id]);

        try {
            $component->set($property, 'Otra persona');
            $this->fail('Expected a rejected identity field.');
        } catch (PublicPropertyNotFoundException $exception) {
            $this->assertStringContainsString($property, $exception->getMessage());
        }
        $this->assertDatabaseCount('incident_logs', 0);
        $this->assertDatabaseCount('incident_comments', 0);
    }
}
