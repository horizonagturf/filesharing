@extends('layout')

@section('content')
    <x-ui.empty-state
        icon="shield-exclamation"
        :title="__('app.cannot-upload')"
        :description="__('app.cannot-upload-blocked-ip')"
    />
@endsection
