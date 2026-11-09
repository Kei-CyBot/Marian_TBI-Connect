<?php

namespace Tests\Feature;

use App\Models\Startup;
use App\Models\TechnicalNeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_analytics_csv_export_returns_a_structured_report(): void
    {
        Startup::ensureConfigured();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $startup = Startup::query()->findOrFail(1);

        TechnicalNeed::query()->create([
            'startup_id' => $startup->id,
            'requirement' => 'Cloud hosting',
            'date_entry' => '2026-10-01',
            'priority' => 'High',
            'status' => 'pending',
            'subscription_price' => '1500.00',
            'resource_tool' => 'Cloud credits',
        ]);
        TechnicalNeed::query()->create([
            'startup_id' => $startup->id,
            'requirement' => 'Workstation',
            'date_entry' => '2026-10-02',
            'priority' => 'Low',
            'status' => 'approved',
            'subscription_price' => '500.00',
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard.export-csv'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            'dashboard_analytics_report_'.now()->format('Y-m-d').'.csv',
            (string) $response->headers->get('content-disposition')
        );

        $content = $response->streamedContent();

        foreach ([
            'Executive Summary',
            'Export Date',
            'Total Uploaded Files',
            'Technical Needs Files',
            'Document Repository Files',
            'Total Startups',
            'Total Contacts',
            'Technical Needs Priority Breakdown',
            'Priority,Count',
            'Low,1',
            'Moderate,0',
            'High,1',
            'Financial & Resource Allocations',
            '"Total Allocated Items",1',
            '"Total Estimated Cost (PHP)",2000.00',
            'Active Technical Requirements List',
            'ID,"Startup/Incubatee Name",Title/Requirement,Priority,Status,"Responsible Person","Estimated Cost (PHP)","Date Entry"',
            '"Cloud hosting",High,pending',
            'Workstation,Low,approved',
        ] as $needle) {
            $this->assertStringContainsString($needle, $content);
        }
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
