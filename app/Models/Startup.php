<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Startup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'founder',
        'contact_email',
        'track',
        'venture_type',
        'status',
        'cohort_id',
        'campus_department',
        'legal_entity_type',
        'founder_contact_id',
        'folder_access_restricted',
        'folder_access_manager_id',
        'google_drive_folder_id',
        'google_drive_folder_url',
        'archived_by',
        'archive_reason',
    ];

    public static function ensureConfigured(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        foreach (config('startups', []) as $startup) {
            static::withTrashed()->firstOrCreate(
                ['id' => $startup['id']],
                [
                    'name' => $startup['name'],
                    'founder' => $startup['founder'],
                    'contact_email' => $startup['email'],
                    'track' => $startup['track'],
                    'venture_type' => $startup['ventureType'] ?? null,
                    'status' => $startup['status'],
                    'cohort_id' => $startup['cohortId'],
                    'campus_department' => $startup['campusDepartment'] ?: null,
                    'legal_entity_type' => $startup['legalEntityType'] ?: null,
                ]
            );
        }
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function contactAssignments(): HasMany
    {
        return $this->hasMany(ContactStartupAssignment::class);
    }

    public function technicalNeeds(): HasMany
    {
        return $this->hasMany(TechnicalNeed::class);
    }

    public function founderContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'founder_contact_id');
    }

    public function folderAccessUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'startup_folder_access')
            ->withPivot('granted_by')
            ->withTimestamps();
    }

    public function userCanAccessFolder(User $user): bool
    {
        if (! $this->folder_access_restricted || $this->folder_access_manager_id === $user->id) {
            return true;
        }

        return $this->folderAccessUsers()->whereKey($user->id)->exists();
    }

    public function userCanManageFolderAccess(User $user): bool
    {
        return $this->folder_access_manager_id === null || $this->folder_access_manager_id === $user->id;
    }
}
