<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
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
        // Keep indexed strings compatible with shared-hosting MySQL/MariaDB
        // installations that still enforce the older 767/1000-byte key limit.
        Schema::defaultStringLength(191);

        Paginator::useBootstrapFive();
    }
}
