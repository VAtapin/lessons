<?php

namespace App\Providers;

use App\Domain\Lessons\BlockRegistry;
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
        $this->app->singleton(BlockRegistry::class, fn () => BlockRegistry::core());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('studio-write', fn (Request $request) => Limit::perMinute(60)->by($request->session()->getId()));
        RateLimiter::for('lesson-poll', fn (Request $request) => Limit::perMinute(120)->by($request->session()->getId()));
        RateLimiter::for('lesson-join', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
    }
}
