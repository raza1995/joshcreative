<?php

namespace App\Providers;

use App\Services\FacebookAdFormatter;
use App\Services\FacebookAdsService;
use Illuminate\Support\ServiceProvider;
use Yajra\DataTables\Html\Builder;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FacebookAdFormatter::class, function ($app) {
            return new FacebookAdFormatter($app->make(FacebookAdsService::class));
        });
        
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    
    }
}
