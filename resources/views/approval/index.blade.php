@extends('layout')

@section('page_title', __('approval.queue-title'))

@section('content')
	<div class="p-5">
		<h2 class="font-title text-2xl mb-5 text-primary font-medium uppercase">
			@lang('approval.queue-title')
		</h2>

		@if ($requests->isEmpty())
			<p class="text-center text-slate-500">@lang('approval.queue-empty')</p>
		@else
			<ul class="divide-y divide-primary-superlight">
				@foreach ($requests as $request)
					<li class="py-4 flex flex-wrap items-center justify-between gap-2">
						<div>
							<p class="font-medium text-primary">
								{{ $request->bundle->title ?? __('approval.untitled-bundle') }}
							</p>
							<p class="text-xs text-slate-500">
								@lang('approval.submitted-by'):
								{{ $request->requester->name ?? $request->requester->username }}
								&middot;
								{{ $request->created_at->diffForHumans() }}
								&middot;
								{{ $request->bundle->files->count() }} @lang('app.files')
							</p>
						</div>
						<a
							href="{{ route('approval.show', $request) }}"
							class="text-sm border px-4 py-2 border-primary rounded hover:bg-primary hover:text-white text-primary"
						>
							@lang('approval.review-bundle')
						</a>
					</li>
				@endforeach
			</ul>
		@endif
	</div>
@endsection
