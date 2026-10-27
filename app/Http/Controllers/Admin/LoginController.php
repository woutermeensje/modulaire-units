<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Auth\LoginController as CustomerLoginController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Separate login for the CMS at /admin/inloggen; only users with is_admin get in.
 */
class LoginController extends CustomerLoginController
{
    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authenticate($request);

        if (! $request->user()->isAdmin()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Dit account heeft geen toegang tot het beheer.',
            ]);
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        parent::destroy($request);

        return redirect()->route('admin.login');
    }
}
