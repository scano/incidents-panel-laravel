<form wire:submit="save" class="form-content">
    <p class="form-intro">Describe qué ocurre. La incidencia se registrará como abierta.</p>
    <x-field name="title" label="Título *"><input id="title" wire:model="title" maxlength="180" required autofocus placeholder="Ej. Error al descargar el informe" aria-describedby="title-error"></x-field>
    <x-field name="description" label="Descripción *"><textarea id="description" wire:model="description" rows="4" maxlength="10000" required placeholder="Qué ocurre, dónde y desde cuándo" aria-describedby="description-error"></textarea></x-field>
    <div class="form-grid">
        <x-field name="requester_name" label="Solicitante *"><input id="requester_name" wire:model="requester_name" maxlength="120" required autocomplete="name" aria-describedby="requester_name-error"></x-field>
        <x-field name="assigned_to" label="Responsable"><input id="assigned_to" wire:model="assigned_to" maxlength="120" placeholder="Sin asignar" aria-describedby="assigned_to-error"></x-field>
    </div>
    <x-field name="priority" label="Prioridad *"><select id="priority" wire:model="priority">@foreach($priorities as $item)<option value="{{ $item->value }}">{{ $item->label() }}</option>@endforeach</select></x-field>
    <div class="form-actions"><span class="muted">* Campos obligatorios</span><button class="button-primary" type="submit" wire:loading.attr="disabled" wire:target="save"><span wire:loading.remove wire:target="save">Crear incidencia</span><span wire:loading wire:target="save">Guardando…</span></button></div>
</form>
