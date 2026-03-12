<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 12
namespace App\Services;

use App\Models\Sku;
use App\Models\AuditLog;
use App\Support\BusinessRules;
use Illuminate\Support\Facades\Log;

class DecayService
{
    /**
     * Patch 3: Citation Decay Self-Healing Loop.
     * Decay status from BusinessRules: decay.week1_status, decay.week2_status, decay.week3_status, decay.week4_status.
     */
    public function processWeeklyDecay(Sku $sku, int $citationScore, string $quorumStatus): bool
    {
        // Patch 2: Decay frozen/paused by quorum rules
        if ($quorumStatus === 'FREEZE' || $quorumStatus === 'PAUSE') {
            return false;
        }

        // Any non-zero citation score self-heals the decay counter & status
        if ($citationScore > 0) {
            $sku->update([
                'decay_weeks'             => 0,
                'decay_consecutive_zeros' => 0,
                'decay_status'            => 'none',
            ]);

            return true;
        }

        // Advance decay (consecutive zero weeks)
        $newZeros = (int) ($sku->decay_consecutive_zeros ?? 0) + 1;

        $yellowFlagWeeks = (int) BusinessRules::get('decay.yellow_flag_weeks');
        $alertWeeks = (int) BusinessRules::get('decay.alert_weeks');
        $autoBriefWeeks = (int) BusinessRules::get('decay.auto_brief_weeks');
        $escalateWeeks = (int) BusinessRules::get('decay.escalate_weeks');

        $status = match (true) {
            $newZeros >= $escalateWeeks => 'escalated',
            $newZeros >= $autoBriefWeeks => 'auto_brief',
            $newZeros >= $alertWeeks    => 'alert',
            $newZeros >= $yellowFlagWeeks => 'yellow_flag',
            default                     => 'none',
        };

        $sku->update([
            'decay_weeks'             => $newZeros, // keep legacy field in sync
            'decay_consecutive_zeros' => $newZeros,
            'decay_status'            => $status,
        ]);

        $this->handleDecayEscalation($sku, $newZeros, $status);

        return true;
    }

    private function handleDecayEscalation(Sku $sku, int $weeks, string $status): void
    {
        $week1 = 'yellow_flag';
        $week2 = 'alert';
        $week3 = 'auto_brief';
        $week4 = 'escalated';
        switch ($status) {
            case $week1:
                $this->notify($sku, 'yellow_flag', "Citation zero in week 1. Monitoring.");
                break;
            case $week2:
                $this->notify($sku, 'alert', "Citation alert! Week 2 with zero visibility.");
                break;
            case $week3:
                $this->generateAutoBrief($sku);
                $this->notify($sku, 'info', "Auto-brief generated due to 3-week citation decay.");
                break;
            case $week4:
                $this->notify($sku, 'error', "CRITICAL: Citation decay escalated to Tier-overseer for {$sku->sku_code} after {$weeks} zero weeks.");
                break;
        }
    }

    private function notify(Sku $sku, string $type, string $message): void
    {
        // Integration with NotificationService (Assumed mock for now)
        \Log::info("Decay Escalation: [{$type}] {$message}", ['sku_id' => $sku->id]);
    }

    private function generateAutoBrief(Sku $sku): void
    {
        $deadlineDays = (int) BusinessRules::get('decay.auto_brief_deadline_days');
        $brief = \App\Models\ContentBrief::create([
            'sku_id' => $sku->id,
            'brief_type' => 'DECAY_REFRESH',
            'title' => 'Auto-brief: 3-week citation decay – ' . ($sku->title ?? $sku->sku_code ?? $sku->id),
            'description' => '3-Week Citation Decay (Auto-generated). Refresh answer block and authority content.',
            'status' => 'OPEN',
            'deadline' => now()->addDays($deadlineDays)->toDateString(),
        ]);

        // Queue brief generation in Python worker so actual brief content is produced
        $pythonUrl = rtrim(env('PYTHON_API_URL', 'http://python-worker:5000'), '/');
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 5.0]);
            $response = $client->post($pythonUrl . '/queue/brief-generation', [
                'json' => [
                    'sku_id' => $sku->id,
                    'title'  => $sku->title ?? $sku->sku_code ?? 'SKU',
                ],
            ]);
            $body = json_decode($response->getBody()->getContents(), true);
            Log::info('Auto-brief queued for SKU after 3-week decay', [
                'sku_id' => $sku->id,
                'sku_code' => $sku->sku_code,
                'brief_id' => $brief->id ?? null,
                'queued' => $body['queued'] ?? false,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue auto-brief for SKU ' . $sku->id . ': ' . $e->getMessage());
        }

        // Log brief_generated in audit_log
        try {
            AuditLog::create([
                'entity_type' => 'brief',
                'entity_id'   => $sku->id,
                'action'      => 'brief_generated',
                'field_name'  => null,
                'old_value'   => null,
                'new_value'   => 'auto_decay_brief',
                'actor_id'    => 'SYSTEM',
                'actor_role'  => 'system',
                'timestamp'   => now(),
                'user_id'     => null,
                'ip_address'  => null,
                'user_agent'  => null,
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // Fail-soft if audit_log columns differ
        }
    }
}
