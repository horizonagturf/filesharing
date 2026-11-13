<?php

namespace App\Mail\Concerns;

use App\Models\Bundle;
use App\Services\BrandingSettings;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

trait UsesBundleOwnerSender
{
    public const OWNER_SENDER_HEADER = 'X-Bundle-Owner-Sender';

    /**
     * When Branding is set to "user" mode, send from the bundle creator instead
     * of the system sender. Falls back to the system sender if the creator has
     * no email address.
     */
    protected function applyBundleOwnerSender(?Bundle $bundle): void
    {
        if (app(BrandingSettings::class)->mailSenderMode() !== BrandingSettings::SENDER_MODE_USER) {
            return;
        }

        $owner = $bundle?->user;

        if ($owner === null || blank($owner->email)) {
            return;
        }

        $address = new Address($owner->email, (string) ($owner->name ?? ''));

        $this->withSymfonyMessage(function (Email $message) use ($address): void {
            $message->from($address);
            $message->getHeaders()->addTextHeader(self::OWNER_SENDER_HEADER, '1');
        });
    }
}
