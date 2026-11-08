<?php

namespace App\Providers;

use Anthropic\Client;
use App\Services\LocalizedLinks;
use App\Support\Locale;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(LocalizedLinks::class);

        $this->app->singleton(Client::class, fn (): Client => new Client(apiKey: config('services.anthropic.key')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The admin, the customer portal and the Dutch site are in Dutch; the English site switches with SetLocale.
        Locale::activate(Locale::SOURCE);

        Paginator::defaultView('partials.paginering');

        Password::defaults(fn () => Password::min(8));

        RateLimiter::for('forms', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
    }
}
