<?php

namespace Tests\Unit;

use App\Exceptions\SsoAuthenticationException;
use App\Services\MicrosoftSsoValidator;
use Illuminate\Support\Facades\Log;
use SocialiteProviders\Manager\OAuth2\User as AzureSocialiteUser;
use Tests\TestCase;

class MicrosoftSsoValidatorTest extends TestCase
{
    private MicrosoftSsoValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new MicrosoftSsoValidator;

        config([
            'sso.tenant_id' => 'expected-tenant-id',
            'sso.allowed_domains' => ['yourcompany.com'],
        ]);
    }

    public function test_accepts_matching_tenant_and_domain(): void
    {
        $azureUser = $this->makeAzureUser(
            email: 'user@yourcompany.com',
            tenantId: 'expected-tenant-id',
        );

        $this->validator->validate($azureUser);

        $this->assertSame('user@yourcompany.com', $this->validator->resolveEmail($azureUser));
        $this->assertSame('expected-tenant-id', $this->validator->resolveTenantId($azureUser));
    }

    public function test_rejects_wrong_tenant(): void
    {
        $azureUser = $this->makeAzureUser(
            email: 'user@yourcompany.com',
            tenantId: 'other-tenant-id',
        );

        $this->expectException(SsoAuthenticationException::class);
        $this->expectExceptionMessage('sso-error-tenant');

        $this->validator->validate($azureUser);
    }

    public function test_logs_tenant_mismatch_when_debug_enabled(): void
    {
        config(['app.debug' => true]);

        Log::shouldReceive('debug')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'tenant mismatch')
                    && $context['expected_tenant_id'] === 'expected-tenant-id'
                    && $context['actual_tenant_id'] === 'other-tenant-id';
            });

        $azureUser = $this->makeAzureUser(
            email: 'user@yourcompany.com',
            tenantId: 'other-tenant-id',
        );

        try {
            $this->validator->validate($azureUser);
        } catch (SsoAuthenticationException) {
            //
        }
    }

    public function test_does_not_log_tenant_mismatch_when_debug_disabled(): void
    {
        config(['app.debug' => false]);

        Log::shouldReceive('debug')->never();

        $azureUser = $this->makeAzureUser(
            email: 'user@yourcompany.com',
            tenantId: 'other-tenant-id',
        );

        try {
            $this->validator->validate($azureUser);
        } catch (SsoAuthenticationException) {
            //
        }
    }

    public function test_rejects_wrong_email_domain(): void
    {
        $azureUser = $this->makeAzureUser(
            email: 'user@other.com',
            tenantId: 'expected-tenant-id',
        );

        $this->expectException(SsoAuthenticationException::class);
        $this->expectExceptionMessage('sso-error-domain');

        $this->validator->validate($azureUser);
    }

    public function test_prefers_mail_field_over_user_principal_name(): void
    {
        $azureUser = $this->makeAzureUser(
            email: 'alias@yourcompany.com',
            tenantId: 'expected-tenant-id',
            rawEmail: 'real.user@yourcompany.com',
        );

        $this->assertSame('real.user@yourcompany.com', $this->validator->resolveEmail($azureUser));
    }

    public function test_resolves_tenant_from_access_token_when_id_token_missing(): void
    {
        $azureUser = $this->makeAzureUser(
            email: 'user@yourcompany.com',
            tenantId: 'expected-tenant-id',
            includeIdToken: false,
        );

        $this->validator->validate($azureUser);

        $this->assertSame('expected-tenant-id', $this->validator->resolveTenantId($azureUser));
    }

    private function makeAzureUser(string $email, string $tenantId, ?string $rawEmail = null, bool $includeIdToken = true): AzureSocialiteUser
    {
        $user = new AzureSocialiteUser;
        $user->map([
            'id' => 'azure-oid-123',
            'name' => 'Test User',
            'email' => $email,
        ]);
        $user->setRaw([
            'id' => 'azure-oid-123',
            'displayName' => 'Test User',
            'userPrincipalName' => $email,
            'mail' => $rawEmail ?? $email,
        ]);

        $tokenBody = [
            'access_token' => $this->fakeIdToken(['tid' => $tenantId]),
        ];

        if ($includeIdToken) {
            $tokenBody['id_token'] = $this->fakeIdToken(['tid' => $tenantId]);
        }

        $user->setAccessTokenResponseBody($tokenBody);

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
