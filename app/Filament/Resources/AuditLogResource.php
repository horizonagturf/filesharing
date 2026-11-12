<?php

namespace App\Filament\Resources;

use App\Enums\AuditEvent;
use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use App\Models\Bundle;
use App\Models\User;
use App\Services\AuditExporter;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?string $modelLabel = 'audit entry';

    protected static ?string $pluralModelLabel = 'audit log';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['bundle', 'actor']))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('audit.columns.created_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_type')
                    ->label(__('audit.columns.event'))
                    ->formatStateUsing(fn (AuditEvent $state) => $state->label())
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bundle.slug')
                    ->label(__('audit.columns.bundle'))
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('actor.username')
                    ->label(__('audit.columns.actor'))
                    ->formatStateUsing(fn (?string $state, AuditLog $record) => $state
                        ?? ($record->actor_type !== null ? $record->actor_type : '—'))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('recipient_email')
                    ->label(__('audit.columns.recipient'))
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ip')
                    ->label(__('audit.columns.ip'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('metadata')
                    ->label('Details')
                    ->formatStateUsing(fn (?array $state) => $state !== null ? json_encode($state) : '—')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->label(__('audit.filters.event_type'))
                    ->options(collect(AuditEvent::cases())->mapWithKeys(
                        fn (AuditEvent $event) => [$event->value => $event->label()]
                    )),
                Tables\Filters\SelectFilter::make('bundle_id')
                    ->label(__('audit.filters.bundle'))
                    ->options(fn () => Bundle::query()->orderByDesc('created_at')->limit(200)->pluck('slug', 'id'))
                    ->searchable(),
                Tables\Filters\SelectFilter::make('actor_id')
                    ->label(__('audit.filters.actor'))
                    ->options(fn () => User::query()->orderBy('username')->pluck('username', 'id'))
                    ->searchable(),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('audit.filters.from')),
                        Forms\Components\DatePicker::make('to')
                            ->label(__('audit.filters.to')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['to'], fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function exportAction(): Action
    {
        return Action::make('export')
            ->label(__('audit.export'))
            ->icon('heroicon-o-arrow-down-tray')
            ->form([
                Forms\Components\DatePicker::make('from')
                    ->label(__('audit.export_form.from')),
                Forms\Components\DatePicker::make('to')
                    ->label(__('audit.export_form.to')),
                Forms\Components\Select::make('format')
                    ->label(__('audit.export_form.format'))
                    ->options([
                        'csv' => 'CSV',
                        'json' => 'JSON',
                    ])
                    ->default(config('audit.export_default_format', 'csv'))
                    ->required(),
            ])
            ->action(function (array $data, AuditExporter $exporter) {
                $from = filled($data['from'] ?? null) ? Carbon::parse($data['from'])->startOfDay() : null;
                $to = filled($data['to'] ?? null) ? Carbon::parse($data['to'])->endOfDay() : null;
                $format = $data['format'] ?? 'csv';

                return $exporter->downloadResponse($format, $from, $to);
            });
    }
}
