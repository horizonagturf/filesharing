<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\SsoAuthenticationException;
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

        $userByOid = User::query()->where('azure_oid', $azureOid)->first();
        $userByEmail = $this->findUserByEmail($email);

        $user = $this->resolveExistingUser($userByOid, $userByEmail);

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
            'name' => filled($name) ? $name : $user->name,
            'last_login_at' => now(),
        ]);

        return $user->fresh();
    }

    private function findUserByEmail(string $email): ?User
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
    }

    private function resolveExistingUser(?User $userByOid, ?User $userByEmail): ?User
    {
        if ($userByOid === null && $userByEmail === null) {
            return null;
        }

        if ($userByOid !== null && $userByEmail !== null && $userByOid->id !== $userByEmail->id) {
            if ($userByEmail->azure_oid === null) {
                $userByOid->update(['azure_oid' => null]);

                return $userByEmail;
            }

            throw new SsoAuthenticationException('sso-error-account-conflict');
        }

        return $userByOid ?? $userByEmail;
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
