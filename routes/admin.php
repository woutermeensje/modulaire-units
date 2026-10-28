<?php

use App\Admin\Registry;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\LoginController as AdminLoginController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PasswordResetController as AdminPasswordResetController;
use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SubmissionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Portal\AccountController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\UsedListingController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Klantomgeving: inloggen, registreren, wachtwoord herstellen
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function (): void {
    Route::get('inloggen', [LoginController::class, 'create'])->name('login');
    Route::post('inloggen', [LoginController::class, 'store'])->name('login.store');
    Route::get('registreren', [RegisterController::class, 'create'])->name('register');
    Route::post('registreren', [RegisterController::class, 'store'])->middleware('throttle:forms')->name('register.store');
    Route::get('wachtwoord-vergeten', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('wachtwoord-vergeten', [PasswordResetController::class, 'email'])->middleware('throttle:forms')->name('password.email');
    Route::get('wachtwoord-herstellen/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('wachtwoord-herstellen', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('uitloggen', [LoginController::class, 'destroy'])->name('logout');
    Route::get('account', DashboardController::class)->name('dashboard');
    Route::get('account/gegevens', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('account/gegevens', [AccountController::class, 'update'])->name('account.update');
    Route::get('account/units', [UsedListingController::class, 'index'])->name('my-used-listings.index');
    Route::get('account/units/aanbieden', [UsedListingController::class, 'create'])->name('my-used-listings.create');
    Route::post('account/units', [UsedListingController::class, 'store'])->middleware('throttle:forms')->name('my-used-listings.store');
});

/*
|--------------------------------------------------------------------------
| Beheer (CMS) — eigen login op /admin/inloggen, alleen voor gebruikers met is_admin
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('inloggen', [AdminLoginController::class, 'create'])->name('login');
        Route::post('inloggen', [AdminLoginController::class, 'store'])->name('login.store');
        Route::get('wachtwoord-reset', [AdminPasswordResetController::class, 'request'])->name('password.request');
        Route::post('wachtwoord-reset', [AdminPasswordResetController::class, 'email'])->middleware('throttle:forms')->name('password.email');
        Route::get('wachtwoord-reset/{token}', [AdminPasswordResetController::class, 'reset'])->name('password.reset');
        Route::post('wachtwoord-reset/nieuw', [AdminPasswordResetController::class, 'update'])->name('password.update');
    });

    Route::post('uitloggen', [AdminLoginController::class, 'destroy'])->middleware('auth')->name('logout');
    Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('home');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureUserIsAdmin::class])->group(function (): void {
    Route::get('dashboard', AdminDashboardController::class)->name('dashboard');

    Route::get('inzendingen', [SubmissionController::class, 'index'])->name('submissions.index');
    Route::get('inzendingen/{submission}', [SubmissionController::class, 'show'])->name('submissions.show');
    Route::get('inzendingen/{submission}/cv', [SubmissionController::class, 'download'])->name('submissions.download');
    Route::delete('inzendingen/{submission}', [SubmissionController::class, 'destroy'])->name('submissions.destroy');

    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::post('media', [MediaController::class, 'store'])->name('media.store');
    Route::get('media/{media}/download', [MediaController::class, 'download'])->name('media.download');
    Route::put('media/{media}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

    Route::get('instellingen', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('instellingen', [SettingsController::class, 'update'])->name('settings.update');

    foreach (Registry::keys() as $key) {
        Route::name("{$key}.")->prefix($key)->group(function () use ($key): void {
            Route::get('/', [ResourceController::class, 'index'])->name('index')->defaults('resourceKey', $key);
            Route::get('nieuw', [ResourceController::class, 'create'])->name('create')->defaults('resourceKey', $key);
            Route::post('/', [ResourceController::class, 'store'])->name('store')->defaults('resourceKey', $key);
            Route::get('{id}', [ResourceController::class, 'edit'])->whereNumber('id')->name('edit')->defaults('resourceKey', $key);
            Route::put('{id}', [ResourceController::class, 'update'])->whereNumber('id')->name('update')->defaults('resourceKey', $key);
            Route::delete('{id}', [ResourceController::class, 'destroy'])->whereNumber('id')->name('destroy')->defaults('resourceKey', $key);
        });
    }
});
