@extends('layout')

@section('page_title', $title)

@section('content')
    <div class="lg:grid lg:grid-cols-4 lg:gap-8">
        <aside class="mb-6 lg:col-span-1 lg:mb-0">
            @include('help._sidebar', ['topics' => $topics, 'currentTopic' => $topic])
        </aside>

        <article class="lg:col-span-3">
            <x-ui.page-header :title="$title" />

            <div class="prose prose-sm max-w-none text-gray-700">
                {!! $body !!}
            </div>
        </article>
    </div>
@endsection
