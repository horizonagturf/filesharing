@extends('layout')

@section('page_title', __('approval.queue-title'))

@section('content')
    <x-ui.page-header :title="__('approval.queue-title')" />

    @if ($requests->isEmpty())
        <x-ui.empty-state
            icon="clipboard-document-list"
            :title="__('approval.queue-empty')"
        />
    @else
        <div class="overflow-hidden rounded-lg ring-1 ring-gray-950/5">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">@lang('app.upload-title')</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">@lang('approval.submitted-by')</th>
                        <th scope="col" class="hidden px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 sm:table-cell">@lang('app.files')</th>
                        <th scope="col" class="hidden px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 md:table-cell">@lang('app.created-at')</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($requests as $request)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="text-sm font-medium text-gray-900">{{ $request->bundle->title ?? __('approval.untitled-bundle') }}</p>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $request->requester->name ?? $request->requester->username }}
                            </td>
                            <td class="hidden px-4 py-3 text-sm text-gray-500 sm:table-cell">
                                {{ $request->bundle->files->count() }}
                            </td>
                            <td class="hidden px-4 py-3 text-sm text-gray-500 md:table-cell">
                                {{ $request->created_at->diffForHumans() }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <x-ui.button variant="ghost" size="sm" href="{{ route('approval.show', $request) }}" icon="chevron-right">
                                    @lang('approval.review-bundle')
                                </x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
