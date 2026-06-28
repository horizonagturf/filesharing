@props([
    'label' => null,
    'for' => null,
    'required' => false,
])

<label @if($for) for="{{ $for }}" @endif {{ $attributes->merge(['class' => 'fi-label']) }}>
    {{ $label ?? $slot }}
    @if ($required)
        <span class="text-red-500">*</span>
    @endif
</label>
