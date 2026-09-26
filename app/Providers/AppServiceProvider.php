<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(function () {
            $rule = Password::min((int) config('cas.auth.password_min_length'))->letters()->mixedCase()->numbers()->symbols();

            // The breach lookup calls an external API, so it only runs in production.
            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
