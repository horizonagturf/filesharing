<?php

namespace App\Filament\Pages;

use App\Services\BrandingSettings;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageBranding extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?string $navigationLabel = 'Branding';

    protected static ?string $title = 'Branding settings';

    protected static string $view = 'filament.pages.manage-branding';

    public ?array $data = [];

    public bool $hadLogoInForm = false;

    public function mount(BrandingSettings $branding): void
    {
        $settings = $branding->all();

        $this->form->fill([
            'app_name' => $settings[BrandingSettings::KEY_APP_NAME] ?? '',
            'logo_path' => $settings[BrandingSettings::KEY_LOGO_PATH] ?? null,
            'primary_color' => $settings[BrandingSettings::KEY_PRIMARY_COLOR] ?? '#7e22ce',
            'accent_color' => $settings[BrandingSettings::KEY_ACCENT_COLOR] ?? '#9333ea',
            'footer_text' => $settings[BrandingSettings::KEY_FOOTER_TEXT] ?? '',
            'tos_url' => $settings[BrandingSettings::KEY_TOS_URL] ?? '',
            'privacy_url' => $settings[BrandingSettings::KEY_PRIVACY_URL] ?? '',
        ]);

        $this->hadLogoInForm = filled($this->data['logo_path'] ?? null);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identity')
                    ->schema([
                        Forms\Components\TextInput::make('app_name')
                            ->label('Application name')
                            ->placeholder(config('app.name'))
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('branding')
                            ->visibility('public')
                            ->maxSize(2048),
                    ]),
                Forms\Components\Section::make('Colors')
                    ->schema([
                        Forms\Components\ColorPicker::make('primary_color')
                            ->label('Primary color')
                            ->required(),
                        Forms\Components\ColorPicker::make('accent_color')
                            ->label('Accent color')
                            ->required(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Footer & legal')
                    ->schema([
                        Forms\Components\Textarea::make('footer_text')
                            ->label('Footer text')
                            ->rows(2)
                            ->maxLength(500),
                        Forms\Components\TextInput::make('tos_url')
                            ->label('Terms of service URL')
                            ->url()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('privacy_url')
                            ->label('Privacy policy URL')
                            ->url()
                            ->maxLength(255),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(BrandingSettings $branding): void
    {
        $data = $this->form->getState();

        $logoPath = $data['logo_path'] ?? null;
        if (is_array($logoPath)) {
            $logoPath = $logoPath[array_key_first($logoPath)] ?? null;
        }

        $branding->set(BrandingSettings::KEY_APP_NAME, $data['app_name'] ?: null);

        if (filled($logoPath)) {
            $branding->set(BrandingSettings::KEY_LOGO_PATH, $logoPath);
        } elseif ($this->hadLogoInForm) {
            $branding->set(BrandingSettings::KEY_LOGO_PATH, null);
        }

        $branding->set(BrandingSettings::KEY_PRIMARY_COLOR, $data['primary_color']);
        $branding->set(BrandingSettings::KEY_ACCENT_COLOR, $data['accent_color']);
        $branding->set(BrandingSettings::KEY_FOOTER_TEXT, $data['footer_text'] ?: null);
        $branding->set(BrandingSettings::KEY_TOS_URL, $data['tos_url'] ?: null);
        $branding->set(BrandingSettings::KEY_PRIVACY_URL, $data['privacy_url'] ?: null);

        Notification::make()
            ->title('Branding saved')
            ->success()
            ->send();
    }
}
