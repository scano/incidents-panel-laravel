<?php

namespace App\Livewire;

use App\Enums\IncidentPriority;
use App\Services\IncidentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CreateIncident extends Component
{
    public string $title = '';

    public string $description = '';

    public string $priority = 'media';

    public string $requester_name = '';

    public string $assigned_to = '';

    /*.s. Exige una sesión autenticada en cada petición del componente. */
    public function boot(): void
    {
        abort_unless(Auth::check(), 403);
    }

    /*.s. Limpia errores anteriores, crea la incidencia con el usuario de la sesión y notifica su creación al panel. */
    public function save(IncidentService $service): void
    {
        $this->resetValidation();
        $incident = $service->create($this->only('title', 'description', 'priority', 'requester_name', 'assigned_to'), Auth::user());
        $this->dispatch('incident-created', id: $incident->id);
    }

    /*.s. Prepara la vista del formulario con las prioridades disponibles. */
    public function render(): View
    {
        return view('livewire.create-incident', ['priorities' => IncidentPriority::cases()]);
    }
}
