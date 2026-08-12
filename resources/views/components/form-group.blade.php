@props(['label', 'name', 'type' => 'text', 'value' => '', 'required' => false, 'help' => '', 'step' => null, 'min' => null])

<div class="mb-3">
    @if ($type !== 'checkbox')
        <label for="{{ $name }}" class="form-label">
            {{ $label }}@if ($required)<span class="text-danger ms-1">*</span>@endif
        </label>
    @endif

    @if ($type === 'textarea')
        <textarea
            class="form-control @error($name) is-invalid @enderror"
            id="{{ $name }}"
            name="{{ $name }}"
            rows="3"
            {{ $required ? 'required' : '' }}
        >{{ old($name, $value) }}{{ $slot->isNotEmpty() ? $slot : '' }}</textarea>
    @elseif ($type === 'select')
        <select
            class="form-select @error($name) is-invalid @enderror"
            id="{{ $name }}"
            name="{{ $name }}"
            {{ $required ? 'required' : '' }}
        >
            {{ $slot }}
        </select>
    @elseif ($type === 'checkbox')
        <div class="form-check">
            <input
                type="checkbox"
                class="form-check-input @error($name) is-invalid @enderror"
                id="{{ $name }}"
                name="{{ $name }}"
                value="1"
                {{ old($name, $value) ? 'checked' : '' }}
            >
            <label class="form-check-label" for="{{ $name }}" style="font-size:0.845rem; color:#374151;">
                {{ $label }}
            </label>
        </div>
    @else
        <input
            type="{{ $type }}"
            class="form-control @error($name) is-invalid @enderror"
            id="{{ $name }}"
            name="{{ $name }}"
            value="{{ old($name, $value) }}"
            {{ $required ? 'required' : '' }}
            {{ $step ? "step=$step" : '' }}
            {{ $min !== null ? "min=$min" : '' }}
        >
    @endif

    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror

    @if ($help)
        <div class="form-text" style="font-size:0.75rem; color:#94a3b8;">{{ $help }}</div>
    @endif
</div>
