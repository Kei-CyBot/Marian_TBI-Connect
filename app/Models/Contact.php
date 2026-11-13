<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Contact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'side',
        'name',
        'email',
        'phone',
        'affiliation_type',
        'role',
        'organization',
        'academic_program',
        'academic_year',
        'domains',
        'domain_other',
        'technical_skills',
        'technical_other',
        'skill_details',
        'nda_status',
        'verification_status',
        'incubatees',
        'activity_log',
        'archived_by',
        'archive_reason',
    ];

    protected function casts(): array
    {
        return [
            'domains' => 'array',
            'technical_skills' => 'array',
            'incubatees' => 'array',
            'activity_log' => 'array',
        ];
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function startupAssignments(): HasMany
    {
        return $this->hasMany(ContactStartupAssignment::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function provisionUserAccount(): ?User
    {
        $email = strtolower(trim((string) $this->email));

        if ($email === '') {
            return null;
        }

        $user = User::withTrashed()
            ->where(fn ($query) => $query
                ->where('contact_id', $this->id)
                ->orWhereRaw('LOWER(email) = ?', [$email]))
            ->first();

        if (! $user) {
            return User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Str::random(32),
                'role' => User::ROLE_MEMBER_INCUBATEE,
                'contact_id' => $this->id,
            ]);
        }

        if ($user->trashed()) {
            $user->restore();
        }

        if ($user->contact_id === null) {
            $user->forceFill(['contact_id' => $this->id])->save();
        }

        return $user;
    }

    public function archiveLinkedUser(): void
    {
        User::query()->where('contact_id', $this->id)->get()->each->delete();
    }

    public function restoreLinkedUser(): void
    {
        User::onlyTrashed()->where('contact_id', $this->id)->get()->each->restore();
    }
}
