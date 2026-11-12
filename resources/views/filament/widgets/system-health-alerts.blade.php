<x-filament-widgets::widget>
    @if (count($alerts))
        <div class="space-y-3">
            @foreach ($alerts as $alert)
                <x-filament::section>
                    <div class="flex items-start gap-3">
                        <x-filament::icon
                            icon="heroicon-m-exclamation-triangle"
                            @class([
                                'h-6 w-6 shrink-0',
                                'text-danger-600 dark:text-danger-400' => $alert['level'] === 'danger',
                                'text-warning-600 dark:text-warning-400' => $alert['level'] === 'warning',
                            ])
                        />
                        <div>
                            <p class="font-semibold text-gray-950 dark:text-white">{{ $alert['title'] }}</p>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $alert['detail'] }}</p>
                        </div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-widgets::widget>
