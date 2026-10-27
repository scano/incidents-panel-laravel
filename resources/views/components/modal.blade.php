@props(['title', 'close', 'drawer' => false])
<dialog wire:ignore.self class="modal {{ $drawer ? 'drawer' : '' }}" aria-labelledby="dialog-title"
    x-data x-init="$el.showModal()" @close="$wire.{{ $close }}()"
    @click="if ($event.target === $el) $el.close()">
    <section class="modal-surface">
        <header class="modal-header">
            <h2 id="dialog-title">{{ $title }}</h2>
            <button type="button" class="icon-button" aria-label="Cerrar" @click="$el.closest('dialog').close()">×</button>
        </header>
        {{ $slot }}
    </section>
</dialog>
