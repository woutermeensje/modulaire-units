<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'Als dit e-mailadres bij ons bekend is, ontvang je binnen enkele minuten een link om je wachtwoord te herstellen.');
    }

    public function reset(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        return $this->resetPassword($request) === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Je wachtwoord is gewijzigd. Je kunt nu inloggen.')
            : back()->withInput($request->only('email'))->withErrors(['email' => 'Deze herstellink is ongeldig of verlopen.']);
    }

    /**
     * Validates the posted token + new password and stores it. Returns the password broker status.
     *
     * @param  array<string, mixed>  $extraCredentials  Extra conditions the user must match, e.g. ['is_admin' => true].
     */
    protected function resetPassword(Request $request, array $extraCredentials = []): string
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        return Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token') + $extraCredentials,
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            }
        );
    }
}
