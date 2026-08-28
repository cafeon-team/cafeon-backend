<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use SocialiteProviders\Kakao\KakaoProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Naver\Provider as NaverProvider;

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
        RateLimiter::for('login', function (Request $request): array {
            $email = Str::lower(trim((string) $request->input('email')));
            $ip = $request->ip();

            return [
                Limit::perMinute(10)->by($email.'|'.$ip),
                Limit::perMinute(50)->by($ip),
            ];
        });

        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('kakao', KakaoProvider::class);
            $event->extendSocialite('naver', NaverProvider::class);
        });
    }
}
