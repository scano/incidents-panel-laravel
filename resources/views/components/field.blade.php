@props(['name', 'label'])
<div class="field">
    <label for="{{ $name }}">{{ $label }}</label>
    {{ $slot }}
    @error($name)<p id="{{ $name }}-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
