<?php

namespace App\Observers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogObserver
{
    public function created(Model $model): void
    {
        $this->log('created', $model);
    }

    public function updated(Model $model): void
    {
        if (empty($model->getChanges())) {
            return;
        }

        $changes = [];
        foreach ($model->getChanges() as $key => $value) {
            if (in_array($key, ['updated_at', 'drive_synced_at'], true)) {
                continue;
            }
            $changes[$key] = [
                'old' => $model->getOriginal($key),
                'new' => $value,
            ];
        }

        if (empty($changes)) {
            return;
        }

        $this->log('updated', $model, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->log('deleted', $model);
    }

    protected function log(string $action, Model $model, array $changes = []): void
    {
        $user = Auth::user();
        $description = $this->describe($action, $model);

        ActivityLog::query()->create([
            'user_id' => $user?->getKey(),
            'action' => $action,
            'subject_type' => get_class($model),
            'subject_id' => $model->getKey(),
            'description' => $description,
            'changes' => empty($changes) ? null : $changes,
            'ip_address' => Request::ip(),
        ]);
    }

    protected function describe(string $action, Model $model): string
    {
        $userName = Auth::user()?->name ?? 'System';
        $class = class_basename($model);

        if ($class === 'FormSubmission') {
            $formName = $model->form_name ?? 'Form';
            $startup = $model->startup_name ?? 'unknown startup';

            return match ($action) {
                'created' => "{$userName} created submission {$formName} for {$startup}",
                'updated' => "{$userName} updated submission {$formName} for {$startup}",
                'deleted' => "{$userName} deleted submission {$formName} for {$startup}",
                default => "{$userName} {$action} submission {$formName} for {$startup}",
            };
        }

        if ($class === 'TechnicalNeed') {
            $requirement = $model->requirement ?? 'technical need';
            $startupName = $model->startup?->name ?? 'unknown startup';

            return match ($action) {
                'created' => "{$userName} created technical need {$requirement} for {$startupName}",
                'updated' => "{$userName} updated technical need {$requirement} for {$startupName}",
                'deleted' => "{$userName} deleted technical need {$requirement} for {$startupName}",
                default => "{$userName} {$action} technical need {$requirement} for {$startupName}",
            };
        }

        return "{$userName} {$action} {$class}";
    }
}
