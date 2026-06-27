<?php

namespace App\Enums;

enum BundleStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Denied = 'denied';
    case Sent = 'sent';
    case Revoked = 'revoked';
}
