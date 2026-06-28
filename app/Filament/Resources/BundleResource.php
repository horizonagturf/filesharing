<?php

namespace App\Filament\Resources;

use App\Enums\BundleStatus;
use App\Filament\Resources\BundleResource\Pages;
use App\Models\Bundle;
use App\Models\User;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BundleResource extends Resource
{
    protected static ?string $model = Bundle::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'slug';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'files', 'recipients']);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Share details')
                    ->schema([
                        Infolists\Components\TextEntry::make('slug'),
                        Infolists\Components\TextEntry::make('title')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge(),
                        Infolists\Components\TextEntry::make('user.username')
                            ->label('Owner')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('fullsize')
                            ->label('Total size')
                            ->formatStateUsing(fn (int $state) => self::formatBytes($state)),
                        Infolists\Components\TextEntry::make('files_count')
                            ->label('File count')
                            ->state(fn (Bundle $record) => $record->files()->count()),
                        Infolists\Components\TextEntry::make('downloads')
                            ->label('Download count'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('expires_at')
                            ->dateTime()
                            ->placeholder('Never'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Recipients')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('recipients')
                            ->schema([
                                Infolists\Components\TextEntry::make('email'),
                                Infolists\Components\TextEntry::make('invited_at')
                                    ->dateTime()
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('verified_at')
                                    ->dateTime()
                                    ->placeholder('—'),
                            ])
                            ->columns(3),
                    ])
                    ->visible(fn (Bundle $record) => $record->recipients()->exists()),
                Infolists\Components\Section::make('Files')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('files')
                            ->schema([
                                Infolists\Components\TextEntry::make('original')
                                    ->label('Name'),
                                Infolists\Components\TextEntry::make('filesize')
                                    ->label('Size')
                                    ->formatStateUsing(fn (int $state) => self::formatBytes($state)),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user')->withCount('files'))
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.username')
                    ->label('Owner')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('files_count')
                    ->label('Files')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fullsize')
                    ->label('Size')
                    ->formatStateUsing(fn (int $state) => self::formatBytes($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(BundleStatus::cases())->mapWithKeys(
                        fn (BundleStatus $status) => [$status->value => str_replace('_', ' ', ucfirst($status->value))]
                    )),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Owner')
                    ->options(fn () => User::query()->orderBy('username')->pluck('username', 'id'))
                    ->searchable(),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Created from'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Created until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBundles::route('/'),
            'view' => Pages\ViewBundle::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return round($value, 1).' '.$units[$unit];
    }
}
