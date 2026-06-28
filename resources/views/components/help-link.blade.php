@props([
    'topic',
])

<a
    href="{{ route('help.show', ['topic' => $topic]) }}"
    {{ $attributes->merge(['class' => 'fi-btn-link']) }}
>
    @lang('help.learn-more')
</a>
