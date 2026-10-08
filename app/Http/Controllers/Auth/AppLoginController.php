<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class AppLoginController extends Controller
{
    /**
     * App-to-app sign-in: another trusted internal system links here with the user's id encrypted
     * by the shared APP_KEY (Crypt::encryptString). No password; the user must still have been
     * granted access to this system and be active.
     */
    public function __invoke(string $id): RedirectResponse|string
    {
        if (Auth::check()) {
            return redirect()->intended(Auth::user()->role->homeUrl());
        }

        try {
            $userId = Crypt::decryptString($id);
        } catch (DecryptException) {
            return 'Login Error [0]. Invalid or tampered ID.';
        }

        $user = User::active()->find($userId);

        if ($user === null) {
            return 'Login Error [2]. No access to this system.';
        }

        Auth::login($user);
        session()->regenerate();

        return redirect()->intended($user->role->homeUrl());
    }
}
