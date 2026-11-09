<?php

namespace Tests\Feature;

use App\Models\Startup;
use App\Models\TechnicalNeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_renders_audit_and_technical_needs_tables(): void
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
            'attachment_path' => 'technical-needs/cloud.pdf',
        ]);

        $this->actingAs($admin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Dashboard Reports')
            ->assertSee('Export to CSV')
            ->assertSee('Uploaded Files & Document Audit Log')
            ->assertSee('Technical Needs & Resource Allocations Report')
            ->assertSee('Cloud hosting')
            ->assertSee(route('reports.export-csv'), false);
    }

    public function test_reports_csv_export_returns_structured_report(): void
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

        $response = $this->actingAs($admin)->get(route('reports.export-csv'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            'tbi_dashboard_report_'.now()->format('Y-m-d').'.csv',
            (string) $response->headers->get('content-disposition')
        );

        $content = $response->streamedContent();

        foreach ([
            'Executive Metrics',
            'Export Date',
            'Total Uploaded Files',
            'Technical Needs Files',
            'Document Repository Files',
            'Total Technical Needs',
            'Pending Technical Needs',
            'Approved Technical Needs',
            '"Total Allocated Items",1',
            '"Total Estimated Cost (PHP)",2000.00',
            'Priority Breakdown',
            'Priority,Count',
            'Low,1',
            'Moderate,0',
            'High,1',
            'Itemized Technical Needs',
            'ID,"Startup/Incubatee Name",Title/Requirement,Priority,Status,"Responsible Person",Resource/Tool,"Estimated Cost (PHP)","Date Entry"',
            '"Cloud hosting",High,pending',
            'Workstation,Low,approved',
        ] as $needle) {
            $this->assertStringContainsString($needle, $content);
        }
    }
}
