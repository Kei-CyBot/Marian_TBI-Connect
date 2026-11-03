<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\FormSubmission;
use App\Models\OutreachMessage;
use App\Models\Startup;
use App\Models\TechnicalNeed;
use App\Models\User;
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
        Event::observe(ActivityLogObserver::class);
        EventInvitation::observe(ActivityLogObserver::class);
        OutreachMessage::observe(ActivityLogObserver::class);
        Startup::observe(ActivityLogObserver::class);
        User::observe(ActivityLogObserver::class);
    }
}
