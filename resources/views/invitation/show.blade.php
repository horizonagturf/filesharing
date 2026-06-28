@extends('layout')

@section('page_title', __('invitation.invitation-title'))

@section('content')
<div class="p-5 max-w-lg mx-auto">
    <h2 class="font-title text-2xl mb-2 text-primary font-medium uppercase">
        @lang('invitation.invitation-title')
    </h2>

    <p class="text-slate-600 mb-4">@lang('invitation.invitation-intro')</p>

    <div class="mb-6 p-4 border border-primary-superlight rounded bg-white">
        <p class="text-xs font-title uppercase text-primary">@lang('invitation.invitation-bundle')</p>
        <p class="font-medium">{{ $bundle->title ?? __('invitation.untitled-bundle') }}</p>
        <p class="text-sm text-slate-500 mt-1">{{ $recipient->email }}</p>
    </div>

    @if (session('status'))
        <div class="mb-4 p-3 rounded bg-green-50 text-green-700 text-sm">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 p-3 rounded bg-red-50 text-red-700 text-sm">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('invitation.otp.request', ['bundle' => $bundle, 'recipient' => $recipient]).'?'.$signedQuery }}" class="mb-6">
        @csrf
        <button type="submit" class="w-full bg-primary text-white rounded px-4 py-2 font-medium">
            @lang('invitation.invitation-request-otp')
        </button>
    </form>

    <form method="POST" action="{{ route('invitation.otp.verify', ['bundle' => $bundle, 'recipient' => $recipient]).'?'.$signedQuery }}">
        @csrf
        <label for="code" class="block font-title uppercase text-sm text-primary mb-1">
            @lang('invitation.invitation-code-label')
        </label>
        <p class="text-xs text-slate-500 mb-2">
            @lang('invitation.invitation-code-help', ['email' => $recipient->email])
        </p>
        <input
            id="code"
            name="code"
            type="text"
            inputmode="numeric"
            pattern="[0-9]{6}"
            maxlength="6"
            required
            class="w-full mb-4 p-2 border border-primary-superlight rounded text-center tracking-widest text-lg"
        />
        <button type="submit" class="w-full bg-primary text-white rounded px-4 py-2 font-medium">
            @lang('invitation.invitation-verify-otp')
        </button>
    </form>
</div>
@endsection
