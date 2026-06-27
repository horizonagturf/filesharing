@extends('layout')

@section('page_title', __('approval.review-bundle'))

@push('scripts')
<script>
	document.addEventListener('alpine:init', () => {
		Alpine.data('review', () => ({
			denyForm: false,
			reason: '',
			error: null,
			modal: { show: false, text: '', action: null },

			showModal: function(text, action) {
				this.modal.text = text
				this.modal.action = action
				this.modal.show = true
			},

			confirmModal: function() {
				this.modal.show = false
				if (this.modal.action) {
					this.modal.action()
				}
			},

			approve: function() {
				this.showModal('{{ __('approval.confirm-approve') }}', () => {
					axios.post('{{ route('approval.approve', $approvalRequest) }}')
						.then(() => { window.location.href = '{{ route('approval.index') }}' })
						.catch((error) => {
							this.error = error.response?.data?.message ?? '{{ __('app.unexpected-error') }}'
						})
				})
			},

			submitDeny: function() {
				if (! this.reason || this.reason.trim().length < 3) {
					this.error = '{{ __('approval.deny-reason-required') }}'
					return
				}

				this.showModal('{{ __('approval.confirm-deny') }}', () => {
					axios.post('{{ route('approval.deny', $approvalRequest) }}', { reason: this.reason })
						.then(() => { window.location.href = '{{ route('approval.index') }}' })
						.catch((error) => {
							this.error = error.response?.data?.message ?? '{{ __('app.unexpected-error') }}'
						})
				})
			},
		}))
	})
</script>
@endpush

@section('content')
	<div x-data="review" class="p-5">
		{{-- Modal --}}
		<template x-if="modal.show">
			<div class="absolute z-40 top-0 left-0 right-0 bottom-0 w-full bg-[#848A97EE]">
				<div class="absolute z-50 top-[50%] left-[50%] translate-x-[-50%] translate-y-[-50%] rounded-lg bg-white w-3/4 md:w-1/2 p-6 text-center shadow-lg border-2 border-gray-300">
					<p class="mt-4 font-title font-medium text-primary text-lg">{{ __('app.confirmation') }}</p>
					<div class="mb-6 text-gray-500" x-text="modal.text"></div>
					<div class="flex justify-center gap-4">
						<button class="bg-gray-300 text-black rounded px-3 py-1" x-on:click="modal.show = false">{{ __('app.cancel') }}</button>
						<button class="bg-primary text-white rounded px-3 py-1" x-on:click="confirmModal()">{{ __('app.confirm') }}</button>
					</div>
				</div>
			</div>
		</template>

		<h2 class="font-title text-2xl mb-2 text-primary font-medium uppercase">
			{{ $approvalRequest->bundle->title ?? __('approval.untitled-bundle') }}
		</h2>

		<p class="text-xs text-slate-500 mb-5">
			@lang('approval.submitted-by'):
			{{ $approvalRequest->requester->name ?? $approvalRequest->requester->username }}
			&middot;
			@lang('approval.submitted-at'): {{ $approvalRequest->created_at->format('Y-m-d H:i') }}
		</p>

		@if ($approvalRequest->bundle->description)
			<div class="mb-5 prose prose-sm max-w-none text-slate-700">
				{!! \Illuminate\Support\Str::markdown($approvalRequest->bundle->description) !!}
			</div>
		@endif

		<h3 class="font-title text-base mb-2 text-primary font-medium uppercase">@lang('app.files-list')</h3>
		<ul class="text-xs mb-8 divide-y divide-gray-100">
			@foreach ($approvalRequest->bundle->files as $file)
				<li class="py-1 flex justify-between">
					<span>{{ $file->original }}</span>
					<span class="text-slate-400">{{ number_format($file->filesize / 1000000, 1) }} MB</span>
				</li>
			@endforeach
		</ul>

		<p x-show="error" x-text="error" class="text-red-600 text-sm mb-4"></p>

		<div class="flex flex-wrap gap-3">
			<button
				x-on:click="approve()"
				class="border px-5 py-2 border-green-600 rounded hover:bg-green-600 hover:text-white text-green-700"
			>
				@lang('approval.approve')
			</button>
			<button
				x-on:click="denyForm = !denyForm"
				class="border px-5 py-2 border-red-600 rounded hover:bg-red-600 hover:text-white text-red-700"
			>
				@lang('approval.deny')
			</button>
			<a href="{{ route('approval.index') }}" class="border px-5 py-2 border-gray-300 rounded text-gray-600 hover:bg-gray-100">
				@lang('app.back')
			</a>
		</div>

		<div x-show="denyForm" x-cloak class="mt-5">
			<label class="font-title uppercase text-sm text-primary block mb-1" for="deny-reason">
				@lang('approval.deny-reason') <span class="text-base">*</span>
			</label>
			<textarea
				id="deny-reason"
				x-model="reason"
				rows="3"
				class="w-full border border-primary-superlight rounded p-2 text-slate-700"
				placeholder="{{ __('approval.deny-reason-placeholder') }}"
			></textarea>
			<button
				x-on:click="submitDeny()"
				class="mt-2 border px-5 py-2 border-red-600 rounded hover:bg-red-600 hover:text-white text-red-700"
			>
				@lang('approval.deny')
			</button>
		</div>
	</div>
@endsection
