<?php

namespace App\Services;

use App\Exceptions\SsoAuthenticationException;
use Illuminate\Support\Facades\Log;
use SocialiteProviders\Manager\OAuth2\User as AzureSocialiteUser;

class MicrosoftSsoValidator
{
    public function validate(AzureSocialiteUser $azureUser): void
    {
        $this->validateTenant($this->resolveTenantId($azureUser));
        $this->validateEmailDomain($this->resolveEmail($azureUser));
    }

    public function resolveTenantId(AzureSocialiteUser $azureUser): string
    {
        $body = $azureUser->accessTokenResponseBody ?? [];

        $tenantId = $this->extractJwtClaim($body['id_token'] ?? null, 'tid');
        if ($tenantId !== null) {
            return $tenantId;
        }

        $tenantId = $this->extractJwtClaim($body['access_token'] ?? null, 'tid');
        if ($tenantId !== null) {
            $this->debugLog('SSO tenant resolved from access_token; id_token was not returned.', [
                'token_response_keys' => array_keys($body),
            ]);

            return $tenantId;
        }

        $this->debugLog('SSO rejected: could not resolve tenant from id_token or access_token.', [
            'token_response_keys' => array_keys($body),
            'hint' => 'Ensure OAuth scopes include openid (configured in config/sso.php).',
        ]);

        throw new SsoAuthenticationException('sso-error-tenant');
    }

    public function resolveEmail(AzureSocialiteUser $azureUser): string
    {
        $raw = $azureUser->getRaw();

        $email = $raw['mail'] ?? $azureUser->getEmail();

        if (! is_string($email) || $email === '' || ! str_contains($email, '@')) {
            $this->debugLog('SSO rejected: no usable email in Azure profile.', [
                'azure_oid' => $azureUser->getId(),
                'user_principal_name' => $raw['userPrincipalName'] ?? null,
                'mail' => $raw['mail'] ?? null,
            ]);

            throw new SsoAuthenticationException('sso-error-email');
        }

        return strtolower($email);
    }

    private function validateTenant(string $tenantId): void
    {
        $expectedTenant = config('sso.tenant_id');

        if (! is_string($expectedTenant) || $expectedTenant === '') {
            $this->debugLog('SSO rejected: AZURE_TENANT_ID is not configured.');

            throw new SsoAuthenticationException('sso-error-config');
        }

        if (! hash_equals(strtolower($expectedTenant), strtolower($tenantId))) {
            $context = [
                'expected_tenant_id' => $expectedTenant,
                'actual_tenant_id' => $tenantId,
            ];

            $this->debugLog('SSO rejected: tenant mismatch. Sign in with a work account from your organization, or verify AZURE_TENANT_ID in .env matches Entra → App registration → Directory (tenant) ID.', $context);

            throw new SsoAuthenticationException('sso-error-tenant', context: $context);
        }
    }

    private function validateEmailDomain(string $email): void
    {
        $allowedDomains = config('sso.allowed_domains', []);

        if ($allowedDomains === []) {
            $this->debugLog('SSO rejected: AZURE_ALLOWED_DOMAINS is not configured.');

            throw new SsoAuthenticationException('sso-error-config');
        }

        $domain = strtolower(substr($email, strrpos($email, '@') + 1));

        foreach ($allowedDomains as $allowedDomain) {
            if ($domain === $allowedDomain) {
                return;
            }
        }

        $context = [
            'email' => $email,
            'email_domain' => $domain,
            'allowed_domains' => $allowedDomains,
        ];

        $this->debugLog('SSO rejected: email domain not in AZURE_ALLOWED_DOMAINS.', $context);

        throw new SsoAuthenticationException('sso-error-domain', context: $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function debugLog(string $message, array $context = []): void
    {
        if (! config('app.debug')) {
            return;
        }

        Log::debug($message, $context);
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }

    private function extractJwtClaim(?string $jwt, string $claim): ?string
    {
        if (! is_string($jwt) || $jwt === '') {
            return null;
        }

        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        if (! is_array($payload) || empty($payload[$claim]) || ! is_string($payload[$claim])) {
            return null;
        }

        return $payload[$claim];
    }
}
