<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\ImageStorage;
use App\Storage\R2ImageStorage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ImageStorage::class, R2ImageStorage::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment(['local', 'testing'])) {
            $this->loadMigrationsFrom(database_path('migrations/reference'));
        }
    }
}
