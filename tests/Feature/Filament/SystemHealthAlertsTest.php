<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\SystemHealthAlerts;
use App\Models\User;
use App\Services\SystemHealth;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class SystemHealthAlertsTest extends TestCase
{
    public function test_alerts_when_scheduler_has_never_run(): void
    {
        $this->actingAsUser(User::factory()->admin()->create());

        Livewire::test(SystemHealthAlerts::class)
            ->assertSee('The scheduler does not appear to be running');
    }

    public function test_no_scheduler_alert_with_fresh_heartbeat(): void
    {
        $this->actingAsUser(User::factory()->admin()->create());
        SystemHealth::recordSchedulerHeartbeat();

        Livewire::test(SystemHealthAlerts::class)
            ->assertDontSee('The scheduler does not appear to be running');
    }

    public function test_alerts_when_scheduler_heartbeat_is_stale(): void
    {
        $this->actingAsUser(User::factory()->admin()->create());
        Cache::forever(SystemHealth::SCHEDULER_KEY, now()->subMinutes(10)->timestamp);

        Livewire::test(SystemHealthAlerts::class)
            ->assertSee('The scheduler does not appear to be running');
    }

    public function test_alerts_when_queue_worker_is_silent(): void
    {
        config(['queue.default' => 'database']);
        $this->actingAsUser(User::factory()->admin()->create());

        Livewire::test(SystemHealthAlerts::class)
            ->assertSee('The queue worker does not appear to be running');
    }

    public function test_queue_check_is_skipped_for_sync_driver(): void
    {
        $this->actingAsUser(User::factory()->admin()->create());

        Livewire::test(SystemHealthAlerts::class)
            ->assertDontSee('queue worker');
    }
}
