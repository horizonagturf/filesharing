<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Exceptions\SsoAuthenticationException;
use App\Models\User;
use App\Services\MicrosoftSsoProvisioner;
use SocialiteProviders\Manager\OAuth2\User as AzureSocialiteUser;
use Tests\TestCase;

class MicrosoftSsoProvisionerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sso.tenant_id' => 'expected-tenant-id',
            'sso.allowed_domains' => ['yourcompany.com'],
        ]);
    }

    public function test_creates_user_on_first_login(): void
    {
        $azureUser = $this->makeAzureUser('new.user@yourcompany.com', 'oid-new-user');

        $user = app(MicrosoftSsoProvisioner::class)->provision($azureUser);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new.user@yourcompany.com',
            'azure_oid' => 'oid-new-user',
            'role' => UserRole::User->value,
        ]);
        $this->assertSame('newuser', $user->username);
        $this->assertNull($user->password);
        $this->assertNotNull($user->last_login_at);
    }

    public function test_updates_existing_user_matched_by_azure_oid(): void
    {
        $existing = User::factory()->create([
            'username' => 'existing',
            'email' => 'existing@yourcompany.com',
            'azure_oid' => 'oid-existing',
            'name' => 'Old Name',
        ]);

        $azureUser = $this->makeAzureUser('existing@yourcompany.com', 'oid-existing', 'Updated Name');

        $user = app(MicrosoftSsoProvisioner::class)->provision($azureUser);

        $this->assertSame($existing->id, $user->id);
        $this->assertSame('Updated Name', $user->name);
        $this->assertNotNull($user->last_login_at);
    }

    public function test_preserves_existing_name_when_azure_returns_empty_string(): void
    {
        $existing = User::factory()->create([
            'username' => 'existing',
            'email' => 'existing@yourcompany.com',
            'azure_oid' => 'oid-existing',
            'name' => 'Old Name',
        ]);

        $azureUser = $this->makeAzureUser('existing@yourcompany.com', 'oid-existing', '');

        $user = app(MicrosoftSsoProvisioner::class)->provision($azureUser);

        $this->assertSame($existing->id, $user->id);
        $this->assertSame('Old Name', $user->name);
    }

    public function test_links_existing_user_by_email(): void
    {
        $existing = User::factory()->create([
            'username' => 'legacyuser',
            'email' => 'legacy@yourcompany.com',
            'azure_oid' => null,
        ]);

        $azureUser = $this->makeAzureUser('legacy@yourcompany.com', 'oid-linked');

        $user = app(MicrosoftSsoProvisioner::class)->provision($azureUser);

        $this->assertSame($existing->id, $user->id);
        $this->assertSame('oid-linked', $user->azure_oid);
    }

    public function test_links_existing_user_by_email_with_legacy_mixed_case(): void
    {
        $existing = User::factory()->create([
            'username' => 'legacyuser',
            'email' => 'Legacy@YourCompany.com',
            'azure_oid' => null,
        ]);

        $azureUser = $this->makeAzureUser('legacy@yourcompany.com', 'oid-linked');

        $user = app(MicrosoftSsoProvisioner::class)->provision($azureUser);

        $this->assertSame($existing->id, $user->id);
        $this->assertSame('oid-linked', $user->azure_oid);
        $this->assertSame('legacy@yourcompany.com', $user->email);
    }

    public function test_updates_existing_user_by_azure_oid_when_email_changed_in_azure(): void
    {
        $existing = User::factory()->create([
            'username' => 'sso-user',
            'email' => 'old@yourcompany.com',
            'azure_oid' => 'oid-sso-user',
        ]);

        $azureUser = $this->makeAzureUser('new@yourcompany.com', 'oid-sso-user', 'SSO User');

        $user = app(MicrosoftSsoProvisioner::class)->provision($azureUser);

        $this->assertSame($existing->id, $user->id);
        $this->assertSame('oid-sso-user', $user->azure_oid);
        $this->assertSame('new@yourcompany.com', $user->email);
    }

    public function test_links_legacy_user_when_oid_and_email_match_different_rows(): void
    {
        $staleOidHolder = User::factory()->create([
            'username' => 'stale',
            'email' => 'stale@yourcompany.com',
            'azure_oid' => 'oid-shared',
        ]);

        $legacy = User::factory()->create([
            'username' => 'legacyuser',
            'email' => 'legacy@yourcompany.com',
            'azure_oid' => null,
        ]);

        $azureUser = $this->makeAzureUser('legacy@yourcompany.com', 'oid-shared');

        $user = app(MicrosoftSsoProvisioner::class)->provision($azureUser);

        $this->assertSame($legacy->id, $user->id);
        $this->assertSame('oid-shared', $user->azure_oid);
        $this->assertNull($staleOidHolder->fresh()->azure_oid);
    }

    public function test_rejects_conflicting_accounts_both_with_azure_oid(): void
    {
        User::factory()->create([
            'username' => 'user-a',
            'email' => 'a@yourcompany.com',
            'azure_oid' => 'oid-a',
        ]);

        User::factory()->create([
            'username' => 'user-b',
            'email' => 'b@yourcompany.com',
            'azure_oid' => 'oid-b',
        ]);

        $azureUser = $this->makeAzureUser('b@yourcompany.com', 'oid-a');

        $this->expectException(SsoAuthenticationException::class);
        $this->expectExceptionMessage('sso-error-account-conflict');

        app(MicrosoftSsoProvisioner::class)->provision($azureUser);
    }

    public function test_rejects_ambiguous_matches_when_or_query_would_return_either_user(): void
    {
        User::factory()->create([
            'username' => 'oid-holder',
            'email' => 'old@yourcompany.com',
            'azure_oid' => 'oid-shared',
        ]);

        User::factory()->create([
            'username' => 'other-oid',
            'email' => 'other@yourcompany.com',
            'azure_oid' => 'oid-other',
        ]);

        $azureUser = $this->makeAzureUser('other@yourcompany.com', 'oid-shared');

        $this->expectException(SsoAuthenticationException::class);
        $this->expectExceptionMessage('sso-error-account-conflict');

        app(MicrosoftSsoProvisioner::class)->provision($azureUser);
    }

    private function makeAzureUser(string $email, string $oid, string $name = 'Test User'): AzureSocialiteUser
    {
        $user = new AzureSocialiteUser;
        $user->map([
            'id' => $oid,
            'name' => $name,
            'email' => $email,
        ]);
        $user->setRaw([
            'id' => $oid,
            'displayName' => $name,
            'userPrincipalName' => $email,
            'mail' => $email,
        ]);
        $user->setAccessTokenResponseBody([
            'id_token' => $this->fakeIdToken(['tid' => 'expected-tenant-id']),
        ]);

        return $user;
    }

    private function fakeIdToken(array $claims): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'none', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode($claims));

        return $header.'.'.$payload.'.signature';
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
