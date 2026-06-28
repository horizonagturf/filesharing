<?php

namespace App\Filament\Pages;

use App\Enums\ShareMode;
use App\Services\SharingSettings;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageSharing extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?string $navigationLabel = 'Sharing';

    protected static ?string $title = 'Sharing settings';

    protected static string $view = 'filament.pages.manage-sharing';

    public ?array $data = [];

    public function mount(SharingSettings $sharing): void
    {
        $this->form->fill([
            'default_share_mode' => $sharing->defaultShareMode()->value,
            'override_blocked_extensions' => $sharing->hasBlockedExtensionsOverride(),
            'blocked_extensions' => $sharing->hasBlockedExtensionsOverride()
                ? $sharing->blockedExtensions()
                : [],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Default share mode')
                    ->description('New bundles inherit this mode. Users can override per bundle when their group allows static links.')
                    ->schema([
                        Forms\Components\Select::make('default_share_mode')
                            ->label('Default share mode')
                            ->options([
                                ShareMode::Invitation->value => 'Invitation + OTP (recommended)',
                                ShareMode::StaticLink->value => 'Static link (less secure)',
                            ])
                            ->required()
                            ->native(false),
                    ]),
                Forms\Components\Section::make('Blocked file types')
                    ->description('Reject uploads whose filename contains a blocked extension.')
                    ->schema([
                        Forms\Components\Toggle::make('override_blocked_extensions')
                            ->label('Override environment default')
                            ->live(),
                        Forms\Components\TagsInput::make('blocked_extensions')
                            ->label('Blocked extensions')
                            ->placeholder('exe')
                            ->helperText(
                                'Environment default: '.implode(', ', app(SharingSettings::class)->envBlockedExtensions())
                            )
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('override_blocked_extensions')),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(SharingSettings $sharing): void
    {
        $data = $this->form->getState();
        $mode = ShareMode::from($data['default_share_mode']);

        $sharing->setDefaultShareMode($mode);

        if ($data['override_blocked_extensions'] ?? false) {
            $sharing->setBlockedExtensions($data['blocked_extensions'] ?? []);
        } else {
            $sharing->setBlockedExtensions(null);
        }

        Notification::make()
            ->title('Sharing settings saved')
            ->success()
            ->send();
    }
}
