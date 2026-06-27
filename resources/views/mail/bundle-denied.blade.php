Your bundle was denied and cannot be shared.

Title: {{ $bundle->title ?? __('approval.untitled-bundle') }}

Reason: {{ $reason }}

You may edit your bundle and submit it again for approval.

Edit: {{ route('upload.create.show', $bundle) }}
