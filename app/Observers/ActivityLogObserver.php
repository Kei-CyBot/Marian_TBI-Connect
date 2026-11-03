<?php

namespace App\Observers;

use App\Models\EventInvitation;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogObserver
{
    private const IGNORED_ATTRIBUTES = [
        'created_at',
        'updated_at',
        'remember_token',
        'password',
        'google_access_token',
        'google_refresh_token',
        'drive_synced_at',
    ];

    public function created(Model $model): void
    {
        $this->log('created', $model);
    }

    public function updated(Model $model): void
    {
        $changes = [];
        foreach ($model->getChanges() as $key => $value) {
            if (in_array($key, self::IGNORED_ATTRIBUTES, true)) {
                continue;
            }
            $changes[$key] = [
                'old' => $model->getOriginal($key),
                'new' => $value,
            ];
        }

        if ($changes === []) {
            return;
        }

        $action = $model instanceof EventInvitation && $model->wasChanged('status')
            ? 'rsvp_response'
            : 'updated';

        $this->log($action, $model, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->log('deleted', $model);
    }

    protected function log(string $action, Model $model, array $changes = []): void
    {
        $user = Auth::user();

        // Event invitations can be answered by guests; everything else only logs real user actions.
        if ($user === null && ! $model instanceof EventInvitation) {
            return;
        }

        if ($action === 'created' && $model instanceof \App\Models\OutreachMessage) {
            $action = 'email_sent';
        }

        ActivityLogger::log($action, $this->describe($action, $model, $user), $model, $changes);
    }

    protected function describe(string $action, Model $model, ?User $user): string
    {
        $userName = $user?->name ?? 'Guest';
        $class = class_basename($model);
        $verb = match ($action) {
            'rsvp_response' => 'responded to',
            'email_sent' => 'sent',
            default => $action,
        };

        return match ($class) {
            'Event' => "{$userName} {$verb} event {$model->title}",
            'EventInvitation' => $action === 'rsvp_response'
                ? "{$userName} {$model->email} responded {$model->status} for {$model->event?->title}"
                : "{$userName} {$verb} invitation for {$model->email}",
            'OutreachMessage' => "{$userName} {$verb} outreach email {$model->subject}",
            'Startup' => "{$userName} {$verb} startup {$model->name}",
            'User' => "{$userName} {$verb} user {$model->name}",
            'FormSubmission' => "{$userName} {$verb} submission ".($model->form_name ?? 'Form').' for '.($model->startup_name ?? 'unknown startup'),
            'TechnicalNeed' => "{$userName} {$verb} technical need ".($model->requirement ?? 'technical need').' for '.($model->startup?->name ?? 'unknown startup'),
            default => "{$userName} {$verb} {$class}",
        };
    }
}
