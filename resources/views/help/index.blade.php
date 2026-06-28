@extends('layout')

@section('page_title', __('help.page-title'))

@section('content')
    @php
        $help = app(\App\Services\HelpContent::class);
    @endphp

    <x-ui.page-header
        :title="__('help.page-title')"
        :subtitle="__('help.page-intro')"
    />

    <div class="grid gap-6 sm:grid-cols-2">
        @foreach ($topics as $slug)
            <a
                href="{{ route('help.show', ['topic' => $slug]) }}"
                class="block rounded-lg p-4 ring-1 ring-gray-200 transition hover:bg-gray-50 hover:ring-primary/30"
            >
                <h2 class="text-sm font-semibold text-gray-900">{{ $help->title($slug) }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $help->description($slug) }}</p>
            </a>
        @endforeach
    </div>
@endsection
