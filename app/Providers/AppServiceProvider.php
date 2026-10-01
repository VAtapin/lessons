<?php

namespace App\Providers;

use App\Application\Account\AccountIdentity;
use App\Domain\Lessons\BlockRegistry;
use Illuminate\Auth\Notifications\ResetPassword;
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
        ResetPassword::createUrlUsing(fn ($user, string $token) => url('/'.AccountIdentity::present($user)['uiLocale'].'/reset-password/'.$token).'?email='.rawurlencode($user->email));
        foreach (config('lessons.auth_limits') as $name => $limits) {
            RateLimiter::for('account-'.$name, function (Request $request) use ($name, $limits): array {
                $email = $request->input('email');
                $identity = in_array($name, ['login', 'forgot'], true)
                    ? hash('sha256', mb_strtolower(trim(is_string($email) ? $email : '')))
                    : (string) ($request->user()?->id ?? $request->ip());
                $response = fn () => response()->json(['error' => ['code' => 'rate_limited']], 429);
                $result = [];
                if (isset($limits['minute'])) {
                    $key = $name === 'resend' ? $name.':'.$identity : $name.':'.$identity.':'.$request->ip();
                    $result[] = Limit::perMinute($limits['minute'])->by($key)->response($response);
                }
                if (isset($limits['hour'])) {
                    $result[] = Limit::perHour($limits['hour'])->by($name.':'.$identity)->response($response);
                    $result[] = Limit::perHour($limits['hour'])->by($name.':'.$request->ip())->response($response);
                }

                return $result;
            });
        }
        RateLimiter::for('studio-write', fn (Request $request) => Limit::perMinute(60)->by($request->session()->getId()));
        RateLimiter::for('lesson-poll', fn (Request $request) => Limit::perMinute(120)->by($request->session()->getId()));
        RateLimiter::for('lesson-join', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
    }
}
