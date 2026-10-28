<div class="detail-content" :class="{ 'scrollable-tab-active': activeTab === 'comments' || activeTab === 'history' }"
     @comment-added="$nextTick(() => $refs.commentsList.scrollTop = 0)" x-data="{
    activeTab: 'data',
    confirmationTarget: '',
    tabs: ['data', 'comments', 'history'],
    selectTab(tab, focus = false) {
        this.activeTab = tab;
        if (focus) this.$refs[tab + 'Tab'].focus();
    },
    moveTab(offset) {
        this.selectTab(this.tabs[(this.tabs.indexOf(this.activeTab) + offset + this.tabs.length) % this.tabs.length], true);
    }
}">
	<header class="detail-heading"><p class="eyebrow">INC-{{ str_pad($incident->id, 4, '0', STR_PAD_LEFT) }}
			<span>· {{ $incident->created_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}</span></p>
		<h3>{{ $incident->title }}</h3>
		<div class="detail-badges">
			<x-status-badge :status="$incident->status"/>
			<span
				class="priority priority-{{ $incident->priority->value }}">Prioridad {{ mb_strtolower($incident->priority->label()) }}</span>
		</div>
	</header>
	@if($notice)
		<p class="notice" role="status">{{ $notice }}</p>
	@endif
	<div class="detail-tabs" role="tablist" aria-label="Secciones de la incidencia"
	     @keydown.arrow-right.prevent="moveTab(1)" @keydown.arrow-left.prevent="moveTab(-1)"
	     @keydown.home.prevent="selectTab('data', true)" @keydown.end.prevent="selectTab('history', true)">
		<button type="button" id="incident-data-tab" x-ref="dataTab" role="tab" aria-controls="incident-data-panel"
		        :aria-selected="activeTab === 'data'" :tabindex="activeTab === 'data' ? 0 : -1"
		        @click="selectTab('data')">Datos de la incidencia
		</button>
		<button type="button" id="incident-comments-tab" x-ref="commentsTab" role="tab"
		        aria-controls="incident-comments-panel"
		        :aria-selected="activeTab === 'comments'" :tabindex="activeTab === 'comments' ? 0 : -1"
		        @click="selectTab('comments')">Comentarios <span
				class="count-pill">{{ $incident->comments->count() }}</span></button>
		<button type="button" id="incident-history-tab" x-ref="historyTab" role="tab"
		        aria-controls="incident-history-panel"
		        :aria-selected="activeTab === 'history'" :tabindex="activeTab === 'history' ? 0 : -1"
		        @click="selectTab('history')">Histórico
		</button>
	</div>
	<section id="incident-data-panel" class="detail-tab-panel" role="tabpanel" aria-labelledby="incident-data-tab"
	         tabindex="0" x-show="activeTab === 'data'">
		<p class="description">{{ $incident->description }}</p>
		<dl class="people">
			<div>
				<dt>Solicitante</dt>
				<dd>{{ $incident->requester_name }}</dd>
			</div>
			<div>
				<dt>Responsable</dt>
				<dd>{{ $incident->assigned_to ?: 'Sin asignar' }}</dd>
			</div>
		</dl>
		<section class="detail-section" aria-labelledby="change-heading">
			<div class="section-heading">
				<h3 id="change-heading">Cambiar estado</h3>
				<!-- <button type="button" class="text-button" wire:click="refreshIncident">Actualizar datos</button> -->
			</div>
			<form @submit.prevent="if (['bloqueada', 'resuelta'].includes($wire.target)) {
                    confirmationTarget = $wire.target;
                    $refs.statusConfirmation.showModal();
                } else {
                    $wire.changeStatus();
                }">
				<div class="form-grid">
					<x-field name="target" label="Nuevo estado">
						<select id="target" wire:model.live="target">
							@foreach($observed->allowedTransitions() as $next)
								<option value="{{ $next->value }}">{{ $next->label() }}</option>
							@endforeach
						</select>
					</x-field>
				</div>
				@if($observed === \App\Enums\IncidentStatus::Resolved)
					<x-field name="reason" label="Motivo de reapertura *">
						<textarea id="reason" rows="2" wire:model="reason" required maxlength="2000" aria-describedby="reason-error"></textarea>
					</x-field>
				@endif

				@error('status')<p class="field-error" role="alert">{{ $message }}</p>@enderror

				<div class="form-actions">
					<button type="submit" class="button-primary" wire:loading.attr="disabled">
						{{ $observed === \App\Enums\IncidentStatus::Resolved ? 'Reabrir incidencia' : 'Guardar estado' }}
					</button>
				</div>
			</form>

            <dialog x-ref="statusConfirmation" wire:ignore.self class="modal status-confirmation"
                    aria-labelledby="status-confirmation-title" aria-describedby="status-confirmation-description"
                    @click="if ($event.target === $el) $el.close()">
                <section class="modal-surface">
                    <header class="modal-header">
                        <h2 id="status-confirmation-title" x-text="confirmationTarget === 'bloqueada' ? 'Bloquear incidencia' : 'Resolver incidencia'"></h2>
                        <button type="button" class="icon-button" aria-label="Cerrar confirmación"
                                @click="$refs.statusConfirmation.close()">×</button>
                    </header>
                    <div class="status-confirmation-body">
                        <p id="status-confirmation-description" x-text="confirmationTarget === 'bloqueada'
                            ? 'La incidencia pasará de En revisión a Bloqueada.'
                            : 'La incidencia se marcará como Resuelta.'"></p>
                        <div class="form-actions">
                            <button type="button" class="button-secondary" autofocus
                                    @click="$refs.statusConfirmation.close()">Cancelar</button>
                            <button type="button" class="button-primary" wire:loading.attr="disabled"
                                    @click="$refs.statusConfirmation.close(); $wire.changeStatus()"
                                    x-text="confirmationTarget === 'bloqueada' ? 'Bloquear incidencia' : 'Resolver incidencia'"></button>
                        </div>
                    </div>
                </section>
            </dialog>
		</section>
	</section>


	<section id="incident-comments-panel" class="detail-tab-panel comments-panel" role="tabpanel"
	         aria-labelledby="incident-comments-tab"
	         tabindex="0" x-show="activeTab === 'comments'" x-cloak>

		<section class="comments-section" aria-labelledby="comments-heading">
			<h3 id="comments-heading">
				Comentarios <span class="count-pill">{{ $incident->comments->count() }}</span>
			</h3>

			<div class="comments" x-ref="commentsList" tabindex="0" role="region" aria-label="Listado de comentarios, del más reciente al más antiguo">
				@forelse($incident->comments as $comment)
					<article wire:key="comment-{{ $comment->id }}" class="comment">
						<header>
							<strong>{{ $comment->author_name }}</strong>
							<time datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->timezone('Europe/Madrid')->format('d/m H:i') }}</time>
						</header>
						<p>{{ $comment->body }}</p></article>
				@empty
					<p class="muted">Todavía no hay comentarios.</p>
				@endforelse
			</div>


			<form wire:submit="addComment" class="comment-form">
				<x-field name="body" label="Comentario *">
						<textarea id="body" wire:model="body" rows="3" required
						          maxlength="10000"
						          placeholder="Añade contexto o un avance del seguimiento…"
						          aria-describedby="body-error">
						</textarea>
				</x-field>
				<button type="submit" class="button-secondary" wire:loading.attr="disabled">Añadir comentario</button>
			</form>

		</section>
	</section>
	<section id="incident-history-panel" class="detail-tab-panel history-panel" role="tabpanel" aria-labelledby="incident-history-tab"
	         tabindex="0" x-show="activeTab === 'history'" x-cloak>
		<section class="history-section" aria-labelledby="history-heading"><h3 id="history-heading">Historial de cambios</h3>
			<div class="history-list" tabindex="0" role="region" aria-label="Listado del historial de cambios">
			<ol class="timeline">
				@foreach($incident->logs->reverse() as $log)
					<li wire:key="log-{{ $log->id }}"><span class="timeline-dot"
					                                        aria-hidden="true"></span><strong>{{ $log->action->label() }}</strong>
						@if($log->duplicate_of_id)
							<p class="history-reason">Duplicado del evento #{{ $log->duplicate_of_id }}. Conservado como
								evidencia; excluido del historial efectivo.</p>
						@endif
						@if($log->action === \App\Enums\IncidentAction::DuplicateMarked)
							<p>Evento #{{ $log->metadata['duplicate_log_id'] }} marcado como duplicado de
								#{{ $log->metadata['canonical_log_id'] }}.</p>
						@endif
						@if($log->old_value)
							<p>{{ \App\Enums\IncidentStatus::tryFrom($log->old_value)?->label() ?? $log->old_value }}
								<span
									aria-hidden="true">→</span> {{ \App\Enums\IncidentStatus::tryFrom($log->new_value)?->label() ?? $log->new_value }}
							</p>
						@endif
						@if($log->metadata['reason'] ?? null)
							<p class="history-reason">{{ $log->metadata['reason'] }}</p>
						@endif
						<small>{{ $log->user_name }} ·
							<time
								datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}</time>
						</small>
					</li>
				@endforeach
			</ol>
			</div>
		</section>
	</section>
</div>
