<?php

namespace App\Enums;

enum AuditEvent: string
{
    case BundleCreated = 'bundle.created';
    case BundleSubmittedForApproval = 'bundle.submitted_for_approval';
    case BundleApproved = 'bundle.approved';
    case BundleDenied = 'bundle.denied';
    case InvitationSent = 'invitation.sent';
    case InvitationRevoked = 'invitation.revoked';
    case OtpRequested = 'otp.requested';
    case OtpVerified = 'otp.verified';
    case OtpFailed = 'otp.failed';
    case BundlePreviewed = 'bundle.previewed';
    case FileDownloaded = 'file.downloaded';
    case BundleZipDownloaded = 'bundle.zip_downloaded';
    case AccessDenied = 'access.denied';
    case AdminBundleRevoked = 'admin.bundle_revoked';
    case SsoLogin = 'sso.login';
    case SsoRejected = 'sso.rejected';

    public function label(): string
    {
        return str_replace(['.', '_'], ' ', ucfirst($this->value));
    }
}
