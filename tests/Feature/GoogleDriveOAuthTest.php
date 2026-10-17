<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleDriveOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_connect_uses_the_configured_marian_tbi_redirect_uri(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.drive_account_email' => 'drive@example.test',
            'services.google.drive_redirect' => 'https://connect.example.test/google-drive/callback',
            'services.google.redirect' => 'https://connect.example.test/auth/google/callback',
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('google-drive.connect'));

        $response->assertRedirect();
        $this->assertStringContainsString(
            'redirect_uri='.urlencode('https://connect.example.test/google-drive/callback'),
            $response->headers->get('Location')
        );
    }
}
