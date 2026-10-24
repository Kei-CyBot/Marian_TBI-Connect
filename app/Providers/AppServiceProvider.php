<?php

namespace App\Providers;

use App\Models\FormSubmission;
use App\Models\TechnicalNeed;
use App\Observers\ActivityLogObserver;
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
        FormSubmission::observe(ActivityLogObserver::class);
        TechnicalNeed::observe(ActivityLogObserver::class);
    }
}
