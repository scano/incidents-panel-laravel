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

    public function boot(): void
    {
        abort_unless(Auth::check(), 403);
    }

    public function save(IncidentService $service): void
    {
        $this->resetValidation();
        $incident = $service->create($this->only('title', 'description', 'priority', 'requester_name', 'assigned_to'), Auth::user());
        $this->dispatch('incident-created', id: $incident->id);
    }

    public function render(): View
    {
        return view('livewire.create-incident', ['priorities' => IncidentPriority::cases()]);
    }
}
