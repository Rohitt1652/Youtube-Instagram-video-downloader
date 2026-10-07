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
        RateLimiter::for('media-info', function (Request $request) {
            $limit = (int) config('media.rate_limits.info_per_minute', 30);
            return Limit::perMinute($limit)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'RATE_LIMIT_EXCEEDED',
                            'message' => 'Too many media info requests. Please wait a moment and try again.',
                        ],
                    ], 429);
                });
        });

        RateLimiter::for('media-download', function (Request $request) {
            $limit = (int) config('media.rate_limits.download_per_minute', 10);
            return Limit::perMinute($limit)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'RATE_LIMIT_EXCEEDED',
                            'message' => 'Too many download requests. Please wait a moment before downloading more files.',
                        ],
                    ], 429);
                });
        });
    }
}
