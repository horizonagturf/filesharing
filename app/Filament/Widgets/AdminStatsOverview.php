<?php

namespace App\Filament\Widgets;

use App\Enums\ApprovalRequestStatus;
use App\Enums\BundleStatus;
use App\Filament\Resources\BundleResource;
use App\Filament\Resources\UserResource;
use App\Models\ApprovalRequest;
use App\Models\Bundle;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $pendingApproval = ApprovalRequest::query()
            ->where('status', ApprovalRequestStatus::Pending)
            ->count();

        $published = Bundle::query()
            ->where('completed', true)
            ->whereIn('status', [BundleStatus::Approved, BundleStatus::Sent])
            ->count();

        $active = Bundle::query()
            ->where('completed', true)
            ->whereIn('status', [BundleStatus::Approved, BundleStatus::Sent])
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();

        $downloads = (int) Bundle::query()->sum('downloads');

        return [
            Stat::make('Users', User::count())
                ->description('Registered accounts')
                ->descriptionIcon('heroicon-m-users')
                ->url(UserResource::getUrl()),

            Stat::make('Pending approval', $pendingApproval)
                ->description('Bundles awaiting review')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingApproval > 0 ? 'warning' : 'success')
                ->url(BundleResource::getUrl('index', [
                    'tableFilters' => [
                        'status' => ['value' => BundleStatus::PendingApproval->value],
                    ],
                ])),

            Stat::make('Published bundles', $published)
                ->description('Approved or sent')
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->url(BundleResource::getUrl()),

            Stat::make('Active bundles', $active)
                ->description('Published and not expired')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Total downloads', number_format($downloads))
                ->description('Across all bundles')
                ->descriptionIcon('heroicon-m-arrow-down-tray'),
        ];
    }
}
