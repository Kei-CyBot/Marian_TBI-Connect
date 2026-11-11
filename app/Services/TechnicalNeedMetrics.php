<?php

namespace App\Services;

use App\Models\TechnicalNeed;

class TechnicalNeedMetrics
{
    public static function summary(): array
    {
        $counts = TechnicalNeed::query()
            ->selectRaw("count(case when attachment_path is not null then 1 end) as request_files")
            ->selectRaw("count(case when approval_attachment_path is not null then 1 end) as approval_files")
            ->selectRaw("count(case when status = 'pending' then 1 end) as pending")
            ->selectRaw("count(case when resource_tool is not null and resource_tool != '' then 1 end) as allocated")
            ->selectRaw('coalesce(sum(subscription_price), 0) as estimated_cost')
            ->first();
        $priorities = TechnicalNeed::query()
            ->selectRaw('priority, count(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority');

        return [
            'fileCount' => (int) ($counts->request_files + $counts->approval_files),
            'pendingCount' => (int) $counts->pending,
            'allocatedItems' => (int) $counts->allocated,
            'estimatedCost' => (float) $counts->estimated_cost,
            'priorities' => [
                'Low' => (int) ($priorities['Low'] ?? 0),
                'Moderate' => (int) ($priorities['Moderate'] ?? 0),
                'High' => (int) ($priorities['High'] ?? 0),
            ],
        ];
    }
}
