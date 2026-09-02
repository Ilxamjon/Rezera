<?php

namespace App\Providers;

use App\Support\Phone\PhoneNormalizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            \App\Contracts\Discovery\BusinessSearchProviderInterface::class,
            \App\Services\Discovery\DatabaseBusinessSearchProvider::class,
        );
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(config('rezera.rate_limits.api'))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            $key = $request->ip();

            if ($request->filled('phone')) {
                try {
                    $key .= '|'.PhoneNormalizer::normalize($request->string('phone')->toString());
                } catch (\InvalidArgumentException) {
                    $key .= '|'.$request->string('phone');
                }
            }

            return Limit::perMinute(config('rezera.rate_limits.auth'))
                ->by($key);
        });

        RateLimiter::for('booking', function (Request $request) {
            return Limit::perMinute(config('rezera.rate_limits.booking'))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('saved-searches', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('webhooks', function (Request $request) {
            $provider = (string) $request->route('provider');

            return Limit::perMinute(120)->by($provider.'|'.$request->ip());
        });
    }
}
