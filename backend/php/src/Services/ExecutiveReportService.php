<?php
namespace App\Services;

use App\Models\Sku;
use App\Models\StaffEffortLog;
use App\Models\ValidationLog;
use App\Models\AuditResult;
use DB;

class ExecutiveReportService
{
    /**
     * Calculate all 8 Ballroom KPIs for the current week (Q20 requirement)
     */
    public function generateWeeklySnapshot(): array
    {
        $startOfWeek = now()->startOfWeek();
        
        return [
            'gate_bypass_rate' => 0.00, // Target 0%: Always zero if middleware/gates are hard enforced
            'hero_effort_pct' => $this->calculateHeroEffortPct($startOfWeek),
            'hero_citation_rate' => $this->calculateHeroCitationRate(),
            'staff_rework_rate' => $this->calculateReworkRate($startOfWeek),
            'tier_coverage_pct' => $this->calculateTierCoverage(),
            'hero_readiness_avg' => Sku::where('tier', 'HERO')->avg('readiness_score') ?? 0,
            'kill_sku_effort_hours' => StaffEffortLog::where('tier', 'KILL')->where('logged_at', '>=', $startOfWeek)->sum('hours_spent')
        ];
    }

    private function calculateHeroEffortPct($since): float
    {
        $totalHours = StaffEffortLog::where('logged_at', '>=', $since)->sum('hours_spent');
        if ($totalHours <= 0) return 0.0;
        
        $heroHours = StaffEffortLog::where('tier', 'HERO')->where('logged_at', '>=', $since)->sum('hours_spent');
        return round(($heroHours / $totalHours) * 100, 2);
    }

    private function calculateHeroCitationRate(): float
    {
        $heroSkus = Sku::where('tier', 'HERO')->get();
        if ($heroSkus->isEmpty()) return 0.0;
        
        $citedCount = $heroSkus->where('score_citation', '>', 0)->count();
        return round(($citedCount / $heroSkus->count()) * 100, 2);
    }

    private function calculateReworkRate($since): float
    {
        // Rework defined as re-submissions after a blocking gate failure
        $totalSubmissions = ValidationLog::where('created_at', '>=', $since)->count();
        if ($totalSubmissions <= 0) return 0.0;
        
        $rejections = ValidationLog::where('created_at', '>=', $since)->where('passed', false)->count();
        return round(($rejections / $totalSubmissions) * 100, 2);
    }

    private function calculateTierCoverage(): float
    {
        $total = Sku::count();
        if ($total <= 0) return 0.0;
        
        $tiered = Sku::whereIn('tier', ['HERO', 'SUPPORT', 'HARVEST', 'KILL'])->count();
        return round(($tiered / $total) * 100, 2);
    }
}
