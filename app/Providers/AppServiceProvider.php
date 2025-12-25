<?php

namespace App\Providers;

use App\Interfaces\OpayInterface;
use App\Repositories\OpayRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            OpayInterface::class,
            function ($app) {
                return new OpayRepository();
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
