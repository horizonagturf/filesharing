<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use SocialiteProviders\Manager\OAuth2\User as AzureSocialiteUser;

class MicrosoftSsoProvisioner
{
    public function __construct(
        private readonly MicrosoftSsoValidator $validator,
    ) {}

    public function provision(AzureSocialiteUser $azureUser): User
    {
        $this->validator->validate($azureUser);

        $azureOid = $azureUser->getId();
        $email = $this->validator->resolveEmail($azureUser);
        $name = $azureUser->getName();

        $user = User::query()
            ->where('azure_oid', $azureOid)
            ->orWhere('email', $email)
            ->first();

        if ($user === null) {
            return User::createWithRoles([
                'username' => $this->deriveUsername($email),
                'name' => $name,
                'email' => $email,
                'azure_oid' => $azureOid,
                'password' => null,
                'requires_approval' => null,
                'last_login_at' => now(),
            ], [UserRole::User]);
        }

        $user->update([
            'azure_oid' => $azureOid,
            'email' => $email,
            'name' => $name ?? $user->name,
            'last_login_at' => now(),
        ]);

        return $user->fresh();
    }

    private function deriveUsername(string $email): string
    {
        $localPart = explode('@', $email)[0];
        $username = preg_replace('/[^a-z0-9]/', '', strtolower($localPart)) ?? '';

        if (strlen($username) < 4) {
            $username = str_pad($username, 4, '0');
        }

        $username = substr($username, 0, 40);
        $base = $username;
        $suffix = 1;

        while (User::query()->where('username', $username)->exists()) {
            $suffixText = (string) $suffix;
            $username = substr($base, 0, 40 - strlen($suffixText)).$suffixText;
            $suffix++;
        }

        return $username;
    }
}
