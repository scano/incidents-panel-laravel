<?php

namespace Tests\Feature;

use App\Livewire\IncidentDashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class IncidentPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_home_page_renders_the_incident_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get(route('incidents.index'))
            ->assertOk()
            ->assertSeeLivewire(IncidentDashboard::class)
            ->assertSee('Panel operativo');
    }
}
