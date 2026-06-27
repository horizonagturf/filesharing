<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use SocialiteProviders\Manager\OAuth2\User as AzureSocialiteUser;
use Tests\TestCase;

class MicrosoftSsoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sso.enabled' => true,
            'sso.tenant_id' => 'expected-tenant-id',
            'sso.allowed_domains' => ['yourcompany.com'],
            'services.azure.client_id' => 'test-client-id',
            'services.azure.client_secret' => 'test-client-secret',
            'services.azure.redirect' => 'http://localhost/auth/microsoft/callback',
            'services.azure.tenant' => 'expected-tenant-id',
        ]);
    }

    public function test_login_page_shows_microsoft_button_when_sso_enabled(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee(__('sso.sign-in-with-microsoft'), false)
            ->assertDontSee('id="user-password"', false);
    }

    public function test_password_login_is_disabled_when_sso_enabled(): void
    {
        User::factory()->create([
            'username' => 'testuser',
            'password' => bcrypt('secret123'),
        ]);

        $this->postJson('/login', [
            'login' => 'testuser',
            'password' => 'secret123',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertForbidden();
    }

    public function test_callback_creates_user_and_logs_in(): void
    {
        $azureUser = $this->makeAzureUser('new.user@yourcompany.com', 'oid-new-user');
        $this->mockSocialiteUser($azureUser);

        $this->get('/auth/microsoft/callback')
            ->assertRedirect(route('homepage'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'new.user@yourcompany.com',
            'azure_oid' => 'oid-new-user',
        ]);
    }

    public function test_callback_rejects_wrong_tenant(): void
    {
        $azureUser = $this->makeAzureUser('user@yourcompany.com', 'oid-user', 'expected-tenant-id', 'wrong-tenant-id');
        $this->mockSocialiteUser($azureUser);

        $this->get('/auth/microsoft/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHas('sso_error');

        $this->assertGuest();
    }

    public function test_callback_rejects_wrong_domain(): void
    {
        $azureUser = $this->makeAzureUser('user@other.com', 'oid-user');
        $this->mockSocialiteUser($azureUser);

        $this->get('/auth/microsoft/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHas('sso_error');

        $this->assertGuest();
    }

    public function test_unauthenticated_upload_blocked_when_sso_enabled(): void
    {
        config(['sharing.upload_ip_limit' => '127.0.0.1']);

        $this->get('/')
            ->assertOk()
            ->assertViewIs('login')
            ->assertSee(__('sso.sign-in-with-microsoft'), false)
            ->assertDontSee('id="user-password"', false);
    }

    public function test_authenticated_user_can_access_homepage_when_sso_enabled(): void
    {
        config(['sharing.upload_ip_limit' => '127.0.0.1']);

        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->get('/')
            ->assertOk();
    }

    public function test_microsoft_routes_return_404_when_sso_disabled(): void
    {
        config(['sso.enabled' => false]);

        $this->get('/auth/microsoft')->assertNotFound();
        $this->get('/auth/microsoft/callback')->assertNotFound();
    }

    private function mockSocialiteUser(AzureSocialiteUser $azureUser): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($azureUser);

        Socialite::shouldReceive('driver')->with('azure')->andReturn($provider);
    }

    private function makeAzureUser(
        string $email,
        string $oid,
        string $tenantId = 'expected-tenant-id',
        ?string $overrideTenantId = null,
    ): AzureSocialiteUser {
        $user = new AzureSocialiteUser;
        $user->map([
            'id' => $oid,
            'name' => 'Test User',
            'email' => $email,
        ]);
        $user->setRaw([
            'id' => $oid,
            'displayName' => 'Test User',
            'userPrincipalName' => $email,
            'mail' => $email,
        ]);
        $user->setAccessTokenResponseBody([
            'id_token' => $this->fakeIdToken(['tid' => $overrideTenantId ?? $tenantId]),
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

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
