<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
use App\Models\Role;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('username')
                    ->disabled()
                    ->dehydrated(false),
                Forms\Components\TextInput::make('name')
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\CheckboxList::make('roles')
                    ->relationship('roles', 'name')
                    ->options(
                        Role::query()->orderBy('slug')->pluck('name', 'id')
                    )
                    ->columns(3)
                    ->required()
                    ->helperText('Every account must include the User role.'),
                Forms\Components\Select::make('requires_approval')
                    ->label('Requires approval')
                    ->options([
                        'inherit' => 'Inherit from groups',
                        'yes' => 'Yes',
                        'no' => 'No',
                    ])
                    ->formatStateUsing(fn (?bool $state) => match ($state) {
                        true => 'yes',
                        false => 'no',
                        default => 'inherit',
                    })
                    ->dehydrateStateUsing(fn ($state) => match ((string) $state) {
                        'yes', '1' => true,
                        'no', '0' => false,
                        default => null,
                    }),
                Forms\Components\Select::make('groups')
                    ->relationship('groups', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->columns([
                Tables\Columns\TextColumn::make('username')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->badge()
                    ->label('Roles'),
                Tables\Columns\TextColumn::make('requires_approval')
                    ->label('Approval')
                    ->formatStateUsing(fn (?bool $state) => match ($state) {
                        true => 'Required',
                        false => 'Not required',
                        default => 'Inherit',
                    }),
                Tables\Columns\TextColumn::make('groups.name')
                    ->badge()
                    ->label('Groups'),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('username')
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Role')
                    ->options(collect(UserRole::cases())->mapWithKeys(
                        fn (UserRole $role) => [$role->value => ucfirst($role->value)]
                    ))
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas(
                            'roles',
                            fn (Builder $roleQuery) => $roleQuery->where('slug', $data['value'])
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListUsers::route('/'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
