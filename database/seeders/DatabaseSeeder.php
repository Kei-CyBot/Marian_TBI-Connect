<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $contact = Contact::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'side' => 'external',
                'name' => 'Test User',
                'affiliation_type' => 'External',
                'role' => 'Member',
                'domains' => [],
                'technical_skills' => [],
                'incubatees' => [],
                'activity_log' => [],
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'role' => User::ROLE_SUPER_ADMIN,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'contact_id' => $contact->id,
            ]
        );
    }
}
