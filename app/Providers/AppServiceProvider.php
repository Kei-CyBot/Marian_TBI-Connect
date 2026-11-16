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
        if (str_starts_with((string) config('app.url'), 'https://')
            || filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOL)) {
            URL::forceScheme('https');
        }

        FormSubmission::observe(ActivityLogObserver::class);
        TechnicalNeed::observe(ActivityLogObserver::class);
        Event::observe(ActivityLogObserver::class);
        EventInvitation::observe(ActivityLogObserver::class);
        OutreachMessage::observe(ActivityLogObserver::class);
        Startup::observe(ActivityLogObserver::class);
        User::observe(ActivityLogObserver::class);
    }
}
