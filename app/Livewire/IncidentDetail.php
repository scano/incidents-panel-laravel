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

    /*.s. Exige una sesión autenticada en cada petición del componente. */
    public function boot(): void
    {
        abort_unless(Auth::check(), 403);
    }

    /*.s. Guarda el ID de la incidencia al abrir el detalle y carga su estado inicial. */
    public function mount(int $incidentId): void
    {
        $this->incidentId = $incidentId;
        $this->refreshIncident();
    }

    /*.s. Actualiza el estado observado desde la base de datos, selecciona la primera transición permitida y limpia errores. */
    public function refreshIncident(): void
    {
        $incident = Incident::findOrFail($this->incidentId);
        $this->observedStatus = $incident->status->value;
        $this->target = $incident->status->allowedTransitions()[0]->value;
        $this->resetValidation();
    }

    /*.s. Guarda el cambio comprobando el estado observado y notifica al panel para cerrar el detalle y actualizar el listado solo si tiene éxito. */
    public function changeStatus(IncidentService $service): void
    {
        $this->reset('notice');
        $this->validate(['target' => ['required', Rule::enum(IncidentStatus::class)]]);

        /*.s. Conserva el estado observado al abrir o actualizar el detalle para detectar cambios de otra sesión. */
        $expected = new Incident;
        $expected->id = $this->incidentId;
        $expected->status = IncidentStatus::from($this->observedStatus);
        $current = $service->changeStatus($expected, IncidentStatus::from($this->target), Auth::user(), $this->reason);

        $this->observedStatus = $current->status->value;
        $this->target = $current->status->allowedTransitions()[0]->value;
        $this->reason = '';
        $this->resetValidation();
        $this->dispatch('incident-updated');
        $this->dispatch('incident-status-changed');
    }

    /*.s. Publica el comentario con el usuario de la sesión, limpia el campo y notifica la actualización sin mostrar confirmación de éxito. */
    public function addComment(IncidentService $service): void
    {
        $this->reset('notice');
        $service->addComment(Incident::findOrFail($this->incidentId), Auth::user(), $this->body);
        $this->body = '';
        $this->resetValidation();
        $this->dispatch('comment-added');
        $this->dispatch('incident-updated');
    }

    /*.s. Carga la incidencia con su historial y comentarios y prepara la vista junto con el estado observado. */
    public function render(): View
    {
        return view('livewire.incident-detail', [
            'incident' => Incident::with(['logs', 'comments'])->findOrFail($this->incidentId),
            'observed' => IncidentStatus::from($this->observedStatus),
        ]);
    }
}
