<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Azure\AzureExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->make('Laravel\Socialite\Contracts\Factory')->extend(
            'azure',
            function ($app) {
                return $app->make('Laravel\Socialite\Contracts\Factory')
                        ->buildProvider(AzureExtendSocialite::class, config('services.azure'));
            }
        );
    }
}
