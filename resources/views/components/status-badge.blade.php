@props(['status'])
<span class="status-badge status-{{ $status->value }}"><span aria-hidden="true" class="status-dot"></span>{{ $status->label() }}</span>
