<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource;
use App\Models\Role;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function afterSave(): void
    {
        if (! $this->record->hasRole(UserRole::User)) {
            $this->record->assignRole(UserRole::User);
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $roleIds = $data['roles'] ?? [];
        $userRoleId = Role::idFor(UserRole::User);

        if (! in_array($userRoleId, $roleIds, false)) {
            $roleIds[] = $userRoleId;
        }

        $data['roles'] = $roleIds;

        return $data;
    }
}
