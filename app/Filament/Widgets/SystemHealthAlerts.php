<?php

namespace App\Filament\Widgets;

use App\Services\SystemHealth;
use Filament\Widgets\Widget;

class SystemHealthAlerts extends Widget
{
    protected static ?int $sort = 0;

    protected static string $view = 'filament.widgets.system-health-alerts';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '60s';

    protected function getViewData(): array
    {
        return ['alerts' => app(SystemHealth::class)->alerts()];
    }
}
