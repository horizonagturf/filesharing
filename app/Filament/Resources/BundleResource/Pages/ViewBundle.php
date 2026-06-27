<?php

namespace App\Filament\Resources\BundleResource\Pages;

use App\Filament\Resources\BundleResource;
use App\Services\BundleAdminService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewBundle extends ViewRecord
{
    protected static string $resource = BundleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('revoke')
                ->label('Revoke')
                ->icon('heroicon-o-no-symbol')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status->value !== 'revoked')
                ->action(function (BundleAdminService $service) {
                    $service->revoke($this->record);
                    $this->refreshFormData(['status']);

                    Notification::make()
                        ->title('Share revoked')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('extendExpiry')
                ->label('Extend expiry')
                ->icon('heroicon-o-clock')
                ->form([
                    Forms\Components\TextInput::make('days')
                        ->label('Extend by (days)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(3650)
                        ->default(30),
                ])
                ->action(function (array $data, BundleAdminService $service) {
                    $service->extendExpiry($this->record, (int) $data['days']);
                    $this->refreshFormData(['expires_at']);

                    Notification::make()
                        ->title('Expiry extended')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('deletePermanently')
                ->label('Delete permanently')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Delete share and files')
                ->modalDescription('This removes the database records and deletes all uploaded files from disk.')
                ->action(function (BundleAdminService $service) {
                    if (! $service->delete($this->record)) {
                        Notification::make()
                            ->title('Could not delete upload files')
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Share deleted')
                        ->success()
                        ->send();

                    $this->redirect(BundleResource::getUrl('index'));
                }),
        ];
    }
}
