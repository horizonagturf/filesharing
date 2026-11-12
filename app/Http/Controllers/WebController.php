<?php

namespace App\Http\Controllers;

use App\Enums\AuditEvent;
use App\Enums\BundleStatus;
use App\Http\Resources\BundleResource;
use App\Models\Bundle;
use App\Services\Audit;
use App\Services\OtpPolicy;
use App\Services\ShareModePolicy;
use App\Support\BundleTokens;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebController extends Controller
{
    public function __construct(
        private readonly ShareModePolicy $shareModePolicy,
        private readonly OtpPolicy $otpPolicy,
    ) {}

    public function homepage()
    {
        $bundles = [];

        if (Auth::check()) {
            $userBundles = Auth::user()->bundles;
            if ($userBundles->isNotEmpty()) {
                $bundles = BundleResource::collection($userBundles)->resolve();
            }
        }

        return view('homepage', [
            'bundles' => $bundles,
        ]);
    }

    public function login()
    {
        return view('login');
    }

    public function doLogin(Request $request)
    {
        abort_if(config('sso.enabled'), 403, 'Password login is disabled when Microsoft SSO is enabled.');

        abort_if(! $request->ajax(), 403);

        $request->validate([
            'login' => 'required|string|min:4|max:40',
            'password' => 'required|min:5|max:100',
        ]);

        if (Auth::attempt([
            'username' => $request->login,
            'password' => $request->password,
        ])) {
            Auth::user()->update(['last_login_at' => now()]);

            return response()->json([
                'result' => true,
            ]);
        }

        return response()->json([
            'result' => false,
            'message' => 'Authentication failed, please try again.',
        ], 403);
    }

    public function newBundle(Request $request)
    {
        abort_if(! $request->ajax(), 403);

        $user = Auth::user();

        try {
            $bundle = new Bundle([
                'user_id' => $user?->id,
                'status' => BundleStatus::Draft,
                'share_mode' => $this->shareModePolicy->effectiveShareMode($user),
                'require_otp' => $this->otpPolicy->defaultRequireOtp(),
                'completed' => false,
                'expiry' => (string) config('sharing.default_expiry', 86400),
                'expires_at' => null,
                'password' => null,
                'slug' => BundleTokens::slug(),
                'owner_token' => BundleTokens::token(),
                'preview_token' => BundleTokens::token(),
                'fullsize' => 0,
                'title' => null,
                'description' => null,
                'max_downloads' => 0,
                'downloads' => 0,
            ]);
            $bundle->save();

            Audit::log(AuditEvent::BundleCreated, [
                'bundle' => $bundle,
                'user' => $user,
            ]);

            $request->attributes->set('bundle_owner_access', true);

            return response()->json([
                'result' => true,
                'redirect' => route('upload.create.show', [
                    'bundle' => $bundle->slug,
                    'auth' => $bundle->owner_token,
                ]),
                'bundle' => new BundleResource($bundle),
            ]);
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'result' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('homepage');
    }
}
