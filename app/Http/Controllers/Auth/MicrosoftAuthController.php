<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditEvent;
use App\Exceptions\SsoAuthenticationException;
use App\Http\Controllers\Controller;
use App\Services\Audit;
use App\Services\MicrosoftSsoProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class MicrosoftAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        abort_unless(config('sso.enabled'), 404);

        return Socialite::driver('azure')
            ->scopes(config('sso.scopes'))
            ->redirect();
    }

    public function callback(MicrosoftSsoProvisioner $provisioner): RedirectResponse
    {
        abort_unless(config('sso.enabled'), 404);

        try {
            $azureUser = Socialite::driver('azure')->user();
            $user = $provisioner->provision($azureUser);

            Auth::login($user);

            Audit::log(AuditEvent::SsoLogin, [
                'user' => $user,
                'metadata' => [
                    'email' => $user->email,
                    'azure_oid' => $user->azure_oid,
                ],
            ]);

            if (config('app.debug')) {
                Log::debug('SSO sign-in succeeded.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'azure_oid' => $user->azure_oid,
                ]);
            }

            return redirect()->intended(route('homepage'));
        } catch (InvalidStateException $exception) {
            if (config('app.debug')) {
                Log::debug('SSO rejected: invalid OAuth state (session expired or CSRF mismatch).', [
                    'message' => $exception->getMessage(),
                ]);
            }

            return $this->redirectWithError('sso-error-state', 'invalid_state');
        } catch (SsoAuthenticationException $exception) {
            return $this->redirectWithError($exception->translationKey, $exception->translationKey);
        } catch (Throwable $exception) {
            report($exception);

            if (config('app.debug')) {
                Log::debug('SSO rejected: unexpected error during callback.', [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }

            return $this->redirectWithError('sso-error-generic', 'unexpected_error');
        }
    }

    private function redirectWithError(string $translationKey, string $reason): RedirectResponse
    {
        Audit::log(AuditEvent::SsoRejected, [
            'metadata' => [
                'reason' => $reason,
                'translation_key' => $translationKey,
            ],
        ]);

        return redirect()
            ->route('login')
            ->with('sso_error', __('sso.'.$translationKey));
    }
}
