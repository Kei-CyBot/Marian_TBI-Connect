<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_analytics_csv_export_redirects_to_the_consolidated_report(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('dashboard.export-csv'))
            ->assertRedirect(route('reports.export-csv'));
    }

    public function test_dashboard_renders_executive_summary_with_export_button(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Executive Summary Analytics')
            ->assertSee('Export Report (CSV)')
            ->assertSee(route('dashboard.export-csv'), false);
    }
}
