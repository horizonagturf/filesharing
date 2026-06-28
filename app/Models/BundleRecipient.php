<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BundleRecipient extends Model
{
    protected $fillable = [
        'bundle_id',
        'email',
        'verified_at',
        'otp_hash',
        'otp_expires_at',
        'otp_attempts',
        'invited_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'invited_at' => 'datetime',
            'otp_attempts' => 'integer',
        ];
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function hasActiveOtp(): bool
    {
        return $this->otp_hash !== null
            && $this->otp_expires_at !== null
            && $this->otp_expires_at->isFuture();
    }
}
