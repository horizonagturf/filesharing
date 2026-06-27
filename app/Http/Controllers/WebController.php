<?php

namespace App\Http\Controllers;

use App\Enums\BundleStatus;
use App\Http\Resources\BundleResource;
use App\Models\Bundle;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebController extends Controller
{
    public function homepage()
    {
        $bundles = [];

        if (Auth::check()) {
            $userBundles = Auth::user()->bundles;
            if ($userBundles->isNotEmpty()) {
                $bundles = BundleResource::collection($userBundles);
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
                'completed' => false,
                'expiry' => config('sharing.default-expiry', 86400),
                'expires_at' => null,
                'password' => null,
                'slug' => substr(sha1(uniqid('slug_', true)), 0, rand(35, 40)),
                'owner_token' => substr(sha1(uniqid('preview_', true)), 0, 15),
                'preview_token' => substr(sha1(uniqid('preview_', true)), 0, 15),
                'fullsize' => 0,
                'title' => null,
                'description' => null,
                'max_downloads' => 0,
                'downloads' => 0,
            ]);
            $bundle->save();

            return response()->json([
                'result' => true,
                'redirect' => route('upload.create.show', ['bundle' => $bundle->slug]),
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
