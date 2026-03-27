<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 12
namespace App\Services;

use App\Models\Sku;
use App\Services\BriefGenerationService;
use App\Support\BusinessRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

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

        // SOURCE: CIE_Master_Developer_Build_Spec.docx §12.2 — thresholds from business_rules only (no silent code fallbacks).
        $yellowFlagWeeks = $this->requireDecayConfigInt('decay.yellow_flag_weeks');
        $alertWeeks = $this->requireDecayConfigInt('decay.alert_weeks');
        $autoBriefWeeks = $this->requireDecayConfigInt('decay.auto_brief_weeks');
        $escalateWeeks = $this->requireDecayConfigInt('decay.escalate_weeks');

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
                // SOURCE: CIE_Master_Developer_Build_Spec.docx §12.2 — Week 1 yellow_flag: no notification, no user-facing action.
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

    /**
     * SOURCE: CIE_Master_Developer_Build_Spec.docx §12.2 — Week 2+ in-app + email to writer/reviewer roles.
     * Week 1 yellow_flag: no call from handleDecayEscalation (no user notification).
     */
    private function notify(Sku $sku, string $type, string $message): void
    {
        $productLabel = (string) ($sku->title ?: $sku->sku_code ?: $sku->id);
        $skuRef = (string) ($sku->sku_code ?: $sku->id);
        $body = $message."\n\nSKU: {$skuRef} — {$productLabel}";

        // TODO: notifications table + UI bell for in-app alerts. Email is primary per §12.2.
        try {
            if (class_exists(\App\Models\Notification::class)) {
                // Reserved for future Notification model + persistence.
            }
        } catch (\Throwable $e) {
            Log::warning('Decay in-app notification skipped: '.$e->getMessage(), ['sku_id' => $sku->id]);
        }

        try {
            $q = DB::table('users')
                ->join('user_roles', 'users.id', '=', 'user_roles.user_id')
                ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                ->whereIn('roles.name', ['CONTENT_EDITOR', 'PRODUCT_SPECIALIST', 'CONTENT_LEAD', 'SEO_GOVERNOR'])
                ->distinct()
                ->select('users.email');
            if (Schema::hasColumn('users', 'is_active')) {
                $q->where('users.is_active', true);
            }
            $emails = $q->pluck('users.email')->filter()->unique()->values()->all();
            if ($emails !== []) {
                Mail::raw($body, function ($m) use ($emails, $type, $productLabel) {
                    $m->to($emails)->subject('[CIE Decay] '.$type.': '.$productLabel);
                });
            }
        } catch (\Throwable $e) {
            Log::error('Decay email failed: '.$e->getMessage(), ['sku_id' => $sku->id]);
        }

        Log::info("Decay Escalation: [{$type}] {$message}", ['sku_id' => $sku->id]);
    }

    private function generateAutoBrief(Sku $sku): void
    {
        // SOURCE: openapi.yaml POST /brief/generate; CIE_v232_Developer_Build_Guide.pdf — week 3 decay uses same brief path as API (BriefGenerationService)
        $failingQuestions = $this->collectFailingQuestionIdsForSku($sku);
        $brief = app(BriefGenerationService::class)->generateDecayRefreshBrief((string) $sku->id, $failingQuestions);

        Log::info('Auto-brief created for SKU after citation decay (BriefGenerationService)', [
            'sku_id' => $sku->id,
            'sku_code' => $sku->sku_code,
            'brief_id' => $brief->id ?? null,
            'failing_questions_count' => count($failingQuestions),
        ]);
    }

    /**
     * SOURCE: CIE_Master_Developer_Build_Spec.docx §12 — golden questions; failing = score 0 on latest audit rows
     *
     * @return list<string>
     */
    private function collectFailingQuestionIdsForSku(Sku $sku): array
    {
        if (!Schema::hasTable('ai_audit_results')) {
            return [];
        }
        $q = DB::table('ai_audit_results')
            ->where('cited_sku_id', $sku->id)
            ->where('score', 0)
            ->orderByDesc('created_at')
            ->limit(80);

        return $q->pluck('question_id')->unique()->values()->map(fn ($id) => (string) $id)->all();
    }

    private function requireDecayConfigInt(string $key): int
    {
        $v = BusinessRules::get($key);
        if ($v === null || $v === '') {
            throw new \RuntimeException('Missing required config: '.$key);
        }

        return (int) $v;
    }
}
