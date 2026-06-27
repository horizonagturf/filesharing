<?php

namespace App\Http\Controllers;

use App\Services\ApprovalPolicy;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    public function show(ApprovalPolicy $approvalPolicy)
    {
        $user = Auth::user();
        $user->load(['groups', 'roles']);

        return view('account', [
            'user' => $user,
            'requiresApproval' => $approvalPolicy->requiresApproval($user),
        ]);
    }
}
