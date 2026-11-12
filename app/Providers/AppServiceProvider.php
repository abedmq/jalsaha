<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        // Keep clients behind one NAT independent; an invalid token cannot consume a valid lease's allowance.
        RateLimiter::for('lease-poll', fn (Request $request) => Limit::perMinute(40)
            ->by(hash('sha256', $request->route('lease').':'.$request->header('X-Lease-Token', ''))));
    }
}
