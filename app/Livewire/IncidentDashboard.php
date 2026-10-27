<?php

namespace App\Livewire;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class IncidentDashboard extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $priority = '';

    #[Locked]
    public ?int $selectedId = null;

    public bool $creating = false;

    public function boot(): void
    {
        abort_unless(Auth::check(), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'priority'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'priority');
        $this->resetPage();
    }

    public function openDetail(int $id): void
    {
        Incident::findOrFail($id);
        $this->selectedId = $id;
    }

    public function closeDetail(): void
    {
        $this->selectedId = null;
    }

    public function closeCreate(): void
    {
        $this->creating = false;
    }

    #[On('incident-created')]
    public function incidentCreated(int $id): void
    {
        $this->creating = false;
        $this->clearFilters();
        $this->selectedId = null;
        session()->flash('notice', 'Incidencia creada correctamente.');
    }

    #[On('incident-updated')]
    public function refreshList(): void
    {
        // The event causes this component to render fresh list data and counts.
    }

    public function render(): View
    {
        $query = Incident::query()
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->priority !== '', fn (Builder $query) => $query->where('priority', $this->priority))
            ->when(trim($this->search) !== '', function (Builder $query) {
                $text = '%'.mb_substr(trim($this->search), 0, 180).'%';
                $query->where(function (Builder $query) use ($text) {
                    $query->where('title', 'like', $text)
                        ->orWhere('description', 'like', $text)
                        ->orWhere('requester_name', 'like', $text)
                        ->orWhere('assigned_to', 'like', $text);
                });
            });

        return view('livewire.incident-dashboard', [
            'incidents' => $query->latest('created_at')->latest('id')->paginate(10),
            'counts' => Incident::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'),
            'statuses' => IncidentStatus::cases(),
            'priorities' => IncidentPriority::cases(),
        ]);
    }
}
