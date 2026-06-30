<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\BundleStatus;
use App\Enums\ShareMode;
use App\Mail\BundleInvitationMail;
use App\Mail\BundleOtpMail;
use App\Models\Bundle;
use App\Models\BundleRecipient;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class BundleInvitationService
{
    public function usesInvitationMode(?Bundle $bundle = null): bool
    {
        if ($bundle !== null) {
            return $bundle->share_mode === ShareMode::Invitation;
        }

        return app(SharingSettings::class)->defaultShareMode() === ShareMode::Invitation;
    }

    public function requiresOtp(Bundle $bundle): bool
    {
        return $this->usesInvitationMode($bundle) && (bool) $bundle->require_otp;
    }

    public function usesManualShareLinks(Bundle $bundle): bool
    {
        return $this->usesInvitationMode($bundle)
            && ! $bundle->require_otp
            && $bundle->recipients()->doesntExist();
    }

    public function requiresRecipients(Bundle $bundle): bool
    {
        return $this->usesInvitationMode($bundle) && (bool) $bundle->require_otp;
    }

    public function grantAccessWithoutOtp(BundleRecipient $recipient): void
    {
        $recipient->update(['verified_at' => now()]);

        RecipientAccess::grant($recipient);

        Audit::log(AuditEvent::OtpVerified, [
            'bundle' => $recipient->bundle,
            'recipient_email' => $recipient->email,
            'metadata' => ['skipped' => true],
        ]);
    }

    /**
     * @param  array<int, string>|string|null  $emails
     */
    public function syncRecipients(Bundle $bundle, array|string|null $emails): void
    {
        if (! $bundle->isEditable()) {
            throw new InvalidArgumentException(__('invitation.bundle-not-editable'));
        }

        $normalized = $this->normalizeEmails($emails);

        $bundle->recipients()
            ->whereNotIn('email', $normalized)
            ->delete();

        foreach ($normalized as $email) {
            $bundle->recipients()->firstOrCreate(['email' => $email]);
        }
    }

    public function sendInvitations(Bundle $bundle): void
    {
        $recipients = $bundle->recipients()->get();

        if ($recipients->isEmpty()) {
            throw new InvalidArgumentException(__('invitation.recipients-required'));
        }

        foreach ($recipients as $recipient) {
            $this->sendInvitation($recipient);
        }

        if ($bundle->status === BundleStatus::Approved) {
            $bundle->update(['status' => BundleStatus::Sent]);
        }
    }

    public function sendInvitation(BundleRecipient $recipient): void
    {
        $recipient->update(['invited_at' => now()]);

        Mail::to($recipient->email)->queue(new BundleInvitationMail($recipient));

        Audit::log(AuditEvent::InvitationSent, [
            'bundle' => $recipient->bundle,
            'recipient_email' => $recipient->email,
        ]);
    }

    public function invitationUrl(BundleRecipient $recipient): string
    {
        return $this->temporarySignedInvitationRoute('invitation.show', $recipient);
    }

    public function otpRequestUrl(BundleRecipient $recipient): string
    {
        return $this->temporarySignedInvitationRoute('invitation.otp.request', $recipient);
    }

    public function otpVerifyUrl(BundleRecipient $recipient): string
    {
        return $this->temporarySignedInvitationRoute('invitation.otp.verify', $recipient);
    }

    public function requestOtp(BundleRecipient $recipient): void
    {
        abort_if(! $this->requiresOtp($recipient->bundle), 404);

        $key = $this->rateLimitKey($recipient);

        if (RateLimiter::tooManyAttempts($key, config('invitation.otp_rate_limit_per_hour', 5))) {
            Audit::denied($recipient->bundle, 'otp_rate_limited', 429, $recipient->email);

            throw new TooManyRequestsHttpException(3600, __('invitation.otp-rate-limited'));
        }

        RateLimiter::hit($key, 3600);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $recipient->update([
            'otp_hash' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(config('invitation.otp_expiry_minutes', 15)),
            'otp_attempts' => 0,
        ]);

        Mail::to($recipient->email)->queue(new BundleOtpMail($recipient, $code));

        Audit::log(AuditEvent::OtpRequested, [
            'bundle' => $recipient->bundle,
            'recipient_email' => $recipient->email,
        ]);
    }

    public function verifyOtp(BundleRecipient $recipient, string $code): void
    {
        abort_if(! $this->requiresOtp($recipient->bundle), 404);

        $code = trim($code);

        if ($code === '') {
            throw new InvalidArgumentException(__('invitation.otp-required'));
        }

        if ($recipient->otp_attempts >= config('invitation.otp_max_attempts', 5)) {
            Audit::log(AuditEvent::OtpFailed, [
                'bundle' => $recipient->bundle,
                'recipient_email' => $recipient->email,
                'metadata' => ['reason' => 'max_attempts'],
            ]);

            throw new InvalidArgumentException(__('invitation.otp-max-attempts'));
        }

        if (! $recipient->hasActiveOtp()) {
            Audit::log(AuditEvent::OtpFailed, [
                'bundle' => $recipient->bundle,
                'recipient_email' => $recipient->email,
                'metadata' => ['reason' => 'expired'],
            ]);

            throw new InvalidArgumentException(__('invitation.otp-expired'));
        }

        if (! Hash::check($code, (string) $recipient->otp_hash)) {
            $recipient->increment('otp_attempts');

            Audit::log(AuditEvent::OtpFailed, [
                'bundle' => $recipient->bundle,
                'recipient_email' => $recipient->email,
                'metadata' => ['reason' => 'invalid_code'],
            ]);

            throw new InvalidArgumentException(__('invitation.otp-invalid'));
        }

        $recipient->update([
            'verified_at' => now(),
            'otp_hash' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
        ]);

        RecipientAccess::grant($recipient);

        Audit::log(AuditEvent::OtpVerified, [
            'bundle' => $recipient->bundle,
            'recipient_email' => $recipient->email,
        ]);
    }

    public function resendInvitation(BundleRecipient $recipient): void
    {
        abort_unless($recipient->bundle->isShareable(), 403);

        $this->sendInvitation($recipient);
    }

    public function previewUrl(Bundle $bundle): string
    {
        return route('bundle.preview', ['bundle' => $bundle]);
    }

    public function downloadUrl(Bundle $bundle): string
    {
        return route('bundle.zip.download', ['bundle' => $bundle]);
    }

    /**
     * @return list<string>
     */
    private function normalizeEmails(array|string|null $emails): array
    {
        if ($emails === null) {
            return [];
        }

        if (is_string($emails)) {
            $emails = preg_split('/[\s,;]+/', $emails) ?: [];
        }

        return collect($emails)
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn (string $email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    private function rateLimitKey(BundleRecipient $recipient): string
    {
        return 'otp-request:'.$recipient->bundle_id.':'.strtolower($recipient->email);
    }

    private function temporarySignedInvitationRoute(string $route, BundleRecipient $recipient): string
    {
        return URL::temporarySignedRoute(
            $route,
            now()->addDays(config('invitation.invitation_link_days', 30)),
            [
                'bundle' => $recipient->bundle,
                'recipient' => $recipient,
            ],
        );
    }
}
