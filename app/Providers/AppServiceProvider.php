<?php

namespace App\Providers;

use App\Repositories\QuranPageRepository;
use App\Services\QuranApiService;
use App\Services\TahfidzProgressService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(QuranPageRepository::class);
        $this->app->singleton(QuranApiService::class);
        $this->app->singleton(TahfidzProgressService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
