<?php

namespace App\Livewire;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Services\IncidentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class IncidentDetail extends Component
{
    #[Locked]
    public int $incidentId;

    #[Locked]
    public string $observedStatus;

    public string $target = '';

    public string $reason = '';

    public string $body = '';

    public string $notice = '';

    public function boot(): void
    {
        abort_unless(Auth::check(), 403);
    }

    public function mount(int $incidentId): void
    {
        $this->incidentId = $incidentId;
        $this->refreshIncident();
    }

    public function refreshIncident(): void
    {
        $incident = Incident::findOrFail($this->incidentId);
        $this->observedStatus = $incident->status->value;
        $this->target = $incident->status->allowedTransitions()[0]->value;
        $this->resetValidation();
    }

    public function changeStatus(IncidentService $service): void
    {
        $this->reset('notice');
        $this->validate(['target' => ['required', Rule::enum(IncidentStatus::class)]]);

        // Keep the status seen when opening or explicitly refreshing the detail.
        $expected = new Incident;
        $expected->id = $this->incidentId;
        $expected->status = IncidentStatus::from($this->observedStatus);
        $current = $service->changeStatus($expected, IncidentStatus::from($this->target), Auth::user(), $this->reason);

        $this->observedStatus = $current->status->value;
        $this->target = $current->status->allowedTransitions()[0]->value;
        $this->reason = '';
        $this->resetValidation();
        $this->notice = 'Estado actualizado. El cambio se ha registrado en el historial.';
        $this->dispatch('incident-updated');
    }

    public function addComment(IncidentService $service): void
    {
        $this->reset('notice');
        $service->addComment(Incident::findOrFail($this->incidentId), Auth::user(), $this->body);
        $this->body = '';
        $this->resetValidation();
        $this->dispatch('comment-added');
        $this->dispatch('incident-updated');
    }

    public function render(): View
    {
        return view('livewire.incident-detail', [
            'incident' => Incident::with(['logs', 'comments'])->findOrFail($this->incidentId),
            'observed' => IncidentStatus::from($this->observedStatus),
        ]);
    }
}
