<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
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
        Model::preventLazyLoading(! app()->isProduction());

        $this->app->booted(function (): void {
            RateLimiter::for('voting-token', function (Request $request): Limit {
                return Limit::perMinute(5)->by($request->ip().'|'.hash('sha256', (string) $request->input('token', '')));
            });

            RateLimiter::for('voting-submit', function (Request $request): Limit {
                return Limit::perMinute(10)->by($request->ip().'|'.$request->session()->get('voting_voter_id', 'guest'));
            });
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
