<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
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
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('kakao', KakaoProvider::class);
            $event->extendSocialite('naver', NaverProvider::class);
        });
    }
}
