@if ($canUseStaticLink || $invitationMode)
<div class="mt-5" @if ($canUseStaticLink) x-show="isInvitationMode()" x-cloak @endif>
    <x-ui.textarea
        id="upload-recipients"
        name="recipients"
        :label="__('invitation.recipients')"
        required
        rows="4"
        :hint="__('invitation.recipients-help')"
        placeholder="colleague@company.com&#10;partner@example.org"
        x-model="bundle.recipients_text"
    />
</div>
@endif
