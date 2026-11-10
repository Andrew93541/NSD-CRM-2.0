<?php

namespace App\Providers;

use App\Models\InventoryStock;
use App\Policies\InventoryPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
        Gate::policy(InventoryStock::class, InventoryPolicy::class);

        if ($this->app->environment('production') || (bool) config('app.force_https', false)) {
            URL::forceScheme('https');
        }
    }
}
