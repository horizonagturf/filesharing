<?php

namespace App\Models;

use App\Enums\ApprovalRequestStatus;
use App\Enums\BundleStatus;
use App\Enums\ShareMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bundle extends Model
{
    protected $fillable = [
        'user_id',
        'slug',
        'title',
        'description',
        'password',
        'owner_token',
        'preview_token',
        'fullsize',
        'max_downloads',
        'downloads',
        'completed',
        'status',
        'share_mode',
        'expiry',
        'expires_at',
        'preview_link',
        'download_link',
        'deletion_link',
    ];

    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'status' => BundleStatus::class,
            'share_mode' => ShareMode::class,
            'expires_at' => 'datetime',
            'fullsize' => 'integer',
            'max_downloads' => 'integer',
            'downloads' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class);
    }

    public function pendingApprovalRequest(): HasOne
    {
        return $this->hasOne(ApprovalRequest::class)
            ->where('status', ApprovalRequestStatus::Pending)
            ->latestOfMany();
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(BundleRecipient::class);
    }

    public function isEditable(): bool
    {
        if ($this->completed) {
            return false;
        }

        return in_array($this->status, [BundleStatus::Draft, BundleStatus::Denied], true);
    }

    public function isShareable(): bool
    {
        if (in_array($this->status, [BundleStatus::Approved, BundleStatus::Sent], true)) {
            return true;
        }

        // Bundles completed before the approval workflow kept draft status.
        return $this->completed && $this->status === BundleStatus::Draft;
    }
}
