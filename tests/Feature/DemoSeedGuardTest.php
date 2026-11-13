<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeedGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_startups_are_not_added_when_startups_exist(): void
    {
        Startup::query()->create([
            'name' => 'Only Co',
            'founder' => 'Only Founder',
            'contact_email' => 'founder@example.test',
            'track' => 'external',
            'status' => 'Applicant',
            'cohort_id' => 1,
        ]);

        Startup::ensureConfigured();

        $this->assertSame(1, Startup::withTrashed()->count());
    }

    public function test_sample_contacts_are_not_added_when_contacts_exist(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        Contact::query()->create([
            'side' => 'external',
            'name' => 'Only Contact',
            'email' => 'only@example.test',
            'affiliation_type' => 'External',
            'role' => 'Member',
            'domains' => [],
            'technical_skills' => [],
            'incubatees' => [],
            'activity_log' => [],
        ]);

        $this->actingAs($admin)->get(route('contacts'))->assertOk();

        $this->assertSame(1, Contact::withTrashed()->count());
    }
}
