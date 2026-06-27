<?php

namespace App\Services;

use App\Enums\BundleStatus;
use App\Models\Bundle;
use Illuminate\Support\Facades\Storage;

class BundleAdminService
{
    public function revoke(Bundle $bundle): void
    {
        $bundle->update(['status' => BundleStatus::Revoked]);
    }

    public function extendExpiry(Bundle $bundle, int $days): void
    {
        $base = ($bundle->expires_at !== null && $bundle->expires_at->isFuture())
            ? $bundle->expires_at->copy()
            : now();

        $bundle->update(['expires_at' => $base->addDays($days)]);
    }

    public function delete(Bundle $bundle): bool
    {
        $uploads = Storage::disk('uploads');

        if ($uploads->exists($bundle->slug) && ! $uploads->deleteDirectory($bundle->slug)) {
            return false;
        }

        foreach ($bundle->files as $file) {
            $file->delete();
        }

        $bundle->delete();

        return true;
    }
}
