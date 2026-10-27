<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Auth\PasswordResetController as CustomerPasswordResetController;
use App\Models\User;
use App\Notifications\AdminResetPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Password reset for the CMS at /admin/wachtwoord-reset; only works for users with is_admin.
 */
class PasswordResetController extends CustomerPasswordResetController
{
    public function request(): View
    {
        return view('admin.auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink(
            $request->only('email') + ['is_admin' => true],
            fn (User $user, string $token) => $user->notify(new AdminResetPassword($token)),
        );

        return back()->with('status', 'Als dit e-mailadres bij een beheerder hoort, ontvang je binnen enkele minuten een link om je wachtwoord te herstellen.');
    }

    public function reset(Request $request, string $token): View
    {
        return view('admin.auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        return $this->resetPassword($request, ['is_admin' => true]) === Password::PASSWORD_RESET
            ? redirect()->route('admin.login')->with('status', 'Je wachtwoord is gewijzigd. Je kunt nu inloggen.')
            : back()->withInput($request->only('email'))->withErrors(['email' => 'Deze herstellink is ongeldig of verlopen.']);
    }
}
