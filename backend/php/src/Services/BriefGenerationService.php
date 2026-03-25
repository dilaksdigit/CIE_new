<?php
// SOURCE: openapi.yaml POST /brief/generate; CIE_v232_Developer_Build_Guide.pdf — decay week 3 and manual brief share one code path
namespace App\Services;

use App\Models\AuditLog;
use App\Models\ContentBrief;
use App\Models\Sku;
use App\Support\BusinessRules;

class BriefGenerationService
{
    /**
     * SOURCE: CIE_Master_Developer_Build_Spec.docx §6.5 — DECAY_REFRESH brief; same persistence as BriefController::generate
     *
     * @param list<string> $failingQuestions
     */
    public function generateDecayRefreshBrief(string $skuId, array $failingQuestions): ContentBrief
    {
        $sku = Sku::findOrFail($skuId);
        $title = 'Decay Refresh: ' . ($sku->title ?: $sku->sku_code ?: $sku->id);

        // SOURCE: CIE_Master_Developer_Build_Spec.docx §6.5
        $brief = ContentBrief::create([
            'sku_id'            => $sku->id,
            'brief_type'        => 'DECAY_REFRESH',
            'priority'          => 'HIGH',
            'title'             => $title,
            'suggested_actions' => array_values(array_filter(array_map('strval', $failingQuestions))),
            'status'            => 'open',
            'deadline'          => now()->addDays((int) BusinessRules::get('decay.auto_brief_deadline_days'))->toDateString(),
        ]);

        try {
            AuditLog::create([
                'entity_type' => 'brief',
                'entity_id'   => $sku->id,
                'action'      => 'brief_generated',
                'field_name'  => null,
                'old_value'   => null,
                'new_value'   => 'auto_decay_brief',
                'actor_id'    => (function_exists('auth') && app()->bound('auth') && auth()->check()) ? (string) auth()->id() : 'SYSTEM',
                'actor_role'  => (function_exists('auth') && app()->bound('auth') && auth()->check())
                    ? (string) (optional(auth()->user()->role)->name ?? 'system')
                    : 'system',
                'timestamp'   => now(),
                'user_id'     => (function_exists('auth') && app()->bound('auth') && auth()->check()) ? auth()->id() : null,
                'ip_address'  => request() ? request()->ip() : null,
                'user_agent'  => request() ? request()->userAgent() : null,
                'created_at'  => now(),
            ]);
        } catch (\Throwable) {
            // Fail-soft if audit_log schema differs
        }

        return $brief;
    }
}
