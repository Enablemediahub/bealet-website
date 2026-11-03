<?php

namespace App\Providers;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\View;
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
        View::composer(['storefront.layout', 'storefront.shop.*'], function ($view): void {
            $view->with('siteSettings', SiteSetting::query()->pluck('setting_value', 'setting_key'));
        });
    }
}
