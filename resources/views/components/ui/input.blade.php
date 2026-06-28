@props([
    'label' => null,
    'required' => false,
    'error' => null,
    'hint' => null,
])

<div {{ $attributes->only('class')->merge(['class' => 'space-y-1']) }}>
    @if ($label)
        <label @if($attributes->has('id')) for="{{ $attributes->get('id') }}" @endif class="fi-label">
            {{ $label }}
            @if ($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    @if ($hint)
        <p class="text-xs text-gray-500">{{ $hint }}</p>
    @endif

    <input {{ $attributes->merge(['class' => 'fi-input' . ($error ? ' fi-input-error' : '')]) }} />

    @if ($error)
        <p class="text-xs text-red-600" role="alert">{{ $error }}</p>
    @endif
</div>
