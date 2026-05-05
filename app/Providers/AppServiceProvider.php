<?php

namespace App\Providers;

use App\Contracts\NotificationProvider;
use App\Services\ExternalNotificationProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationProvider::class, ExternalNotificationProvider::class);
    }

    public function boot(): void
    {
        //
    }
}
