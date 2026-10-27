<div class="app-shell"
     x-data
     x-effect="[$wire.search, $wire.status, $wire.priority, $wire.paginators.page]; $nextTick(() => $refs.incidentList.scrollTop = 0)"
     @incident-created.window="$nextTick(() => $refs.incidentList.scrollTop = 0)">

	<main id="workspace" class="workspace">

		<div class="topline">
			<span>Operaciones <span aria-hidden="true">/</span> <strong>Incidencias</strong></span>
			<div class="session-toolbar">
				<span class="session-user">{{ auth()->user()->name }}</span>
				<form method="POST" action="{{ route('logout') }}">
					@csrf
					<button type="submit" class="text-button">Cerrar sesión</button>
				</form>
			</div>
		</div>

		<header class="page-header">
			<div>
				<p class="eyebrow">CONTROL Y SEGUIMIENTO</p>
				<h1>Panel de incidencias</h1>
				<p>Consulta, prioriza y resuelve las incidencias de tu equipo.</p>
			</div>
			<button type="button" class="button-primary" wire:click="$set('creating', true)">
				<span aria-hidden="true">＋</span> Nueva incidencia
			</button>
		</header>

		@if(session('notice'))
			<p class="notice" role="status">{{ session('notice') }}</p>
		@endif

		<section class="status-overview" aria-label="Resumen global de incidencias por estado">
			@foreach($statuses as $item)
				<button type="button" wire:key="count-{{ $item->value }}"
				        class="status-summary {{ $status === $item->value ? 'is-active' : '' }}"
				        wire:click="$set('status', '{{ $status === $item->value ? '' : $item->value }}')"
				        aria-pressed="{{ $status === $item->value ? 'true' : 'false' }}">
					<span class="summary-label"><span class="status-dot status-{{ $item->value }}" aria-hidden="true"></span>{{ $item->label() }}</span>
					<span class="summary-value">{{ $counts[$item->value] ?? 0 }}</span>
				</button>
			@endforeach
		</section>

		<section class="list-section" aria-labelledby="list-heading">
			<div class="section-heading">
				<h2 id="list-heading">Todas las incidencias
					<span class="count-pill">{{ $incidents->total() }}</span>
				</h2>
				<span class="muted">Más recientes primero</span>
			</div>
			<div class="filters">
				<div class="search-field">
					<label class="sr-only" for="search">Buscar incidencias</label>
					<span aria-hidden="true">⌕</span>
					<input id="search" type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar por título, descripción o persona…" maxlength="180">
				</div>
				<div>
					<label class="sr-only" for="status">Filtrar por estado</label>
					<select id="status" wire:model.live="status">
						<option value="">Todos los estados</option>
						@foreach($statuses as $item)
							<option value="{{ $item->value }}">{{ $item->label() }}</option>
						@endforeach
					</select>
				</div>

				<div>
					<label class="sr-only" for="priority-filter">Filtrar por prioridad</label>
					<select id="priority-filter" wire:model.live="priority">
						<option value="">Todas las prioridades</option>
						@foreach($priorities as $item)
							<option value="{{ $item->value }}">{{ $item->label() }}</option>
						@endforeach
					</select>
				</div>

				@if($search !== '' || $status !== '' || $priority !== '')
					<button type="button" class="text-button" wire:click="clearFilters">Limpiar filtros</button>
				@endif
			</div>

			<p class="sr-only" role="status" wire:loading>Actualizando incidencias…</p>

			<div class="incident-list" x-ref="incidentList" tabindex="0" role="region" aria-label="Listado de incidencias" wire:loading.class="is-loading">
				<div class="list-labels" aria-hidden="true">
					<span>INCIDENCIA</span><span>ESTADO</span><span>PRIORIDAD</span><span>RESPONSABLE</span><span>CREADA</span>
				</div>

				@forelse($incidents as $incident)
					<article wire:click="openDetail({{ $incident->id }})" wire:key="incident-{{ $incident->id }}" class="incident-row {{ $incident->status === \App\Enums\IncidentStatus::Blocked ? 'is-blocked' : '' }}">
						<div class="incident-title">
							<span class="incident-number">INC-{{ str_pad($incident->id, 4, '0', STR_PAD_LEFT) }}</span>
							<h3>{{ $incident->title }}</h3>
							<p>{{ $incident->requester_name }}</p></div>
						<div>
							<x-status-badge :status="$incident->status"/>
						</div>
						<div class="priority priority-{{ $incident->priority->value }}">
							<span aria-hidden="true">{{ $incident->priority->symbol() }}</span>
							{{ $incident->priority->label() }}
						</div>
						<div class="assignee">
							<span class="avatar" aria-hidden="true">
								{{ mb_strtoupper(mb_substr($incident->assigned_to ?: $incident->requester_name, 0, 1)) }}
							</span> <span>{{ $incident->assigned_to ?: 'Sin asignar' }}</span>
						</div>
						<time class="row-date" datetime="{{ $incident->created_at->toIso8601String() }}">
							{{ $incident->created_at->timezone('Europe/Madrid')->format('d/m/Y') }}
						</time>
					</article>

					@empty
					<div class="empty-state">
						<span aria-hidden="true">⌕</span>
						<h3>{{ $search || $status || $priority ? 'No hay coincidencias' : 'Todo empieza con un registro' }}</h3>
						<p>{{ $search || $status || $priority ? 'Prueba con otro texto o elimina los filtros.' : 'Crea la primera incidencia para empezar el seguimiento.' }}</p>@if($search || $status || $priority)
							<button type="button" class="button-secondary" wire:click="clearFilters">Limpiar filtros
							</button>
						@else
							<button type="button" class="button-primary" wire:click="$set('creating', true)">Nueva
								incidencia
							</button>
						@endif
					</div>
				@endforelse
			</div>
			<footer class="list-footer">
				{{ $incidents->onEachSide(1)->links('pagination.incidents') }}
			</footer>
		</section>
	</main>

	@if($creating)
		<x-modal title="Nueva incidencia" close="closeCreate">
			<livewire:create-incident/>
		</x-modal>
	@endif

	@if($selectedId)
		<x-modal title="Detalle de incidencia" close="closeDetail" :drawer="true">
			<livewire:incident-detail :incident-id="$selectedId" :key="'detail-'.$selectedId"/>
		</x-modal>
	@endif
</div>
