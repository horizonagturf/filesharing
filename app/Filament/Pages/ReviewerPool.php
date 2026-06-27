<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ReviewerPool extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Reviewers';

    protected static ?string $title = 'Reviewer pool';

    protected static string $view = 'filament.pages.reviewer-pool';

    public static function getNavigationLabel(): string
    {
        return 'Reviewers';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->whereHas('roles', fn ($query) => $query->where('slug', UserRole::Reviewer->value))
                    ->orderBy('name')
                    ->orderBy('username')
            )
            ->columns([
                Tables\Columns\TextColumn::make('username')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable(),
            ])
            ->paginated([10, 25, 50])
            ->defaultSort('username');
    }
}
