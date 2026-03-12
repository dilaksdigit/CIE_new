<?php
// SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 4

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FAQService
{
    /**
     * Returns faq_templates rows filtered by cluster_id and intent_key (each optional).
     * ORDER BY display_order ASC.
     */
    public function getTemplates(?int $clusterId, ?string $intentKey): array
    {
        $query = DB::table('faq_templates')
            ->orderBy('display_order', 'asc');

        if ($clusterId !== null) {
            $query->where('cluster_id', $clusterId);
        }
        if ($intentKey !== null) {
            $query->where('intent_key', $intentKey);
        }

        $rows = $query->get();
        return $rows->map(fn ($row) => (array) $row)->values()->all();
    }

    /**
     * Saves FAQ responses for a SKU. UPSERT per response; INSERT into audit_log per save (immutable).
     */
    public function saveResponses($skuId, array $responses): void
    {
        $skuIdStr = (string) $skuId;
        foreach ($responses as $item) {
            $templateId = $item['template_id'] ?? null;
            $answer = $item['answer'] ?? '';
            if (!array_key_exists('template_id', $item)) {
                continue;
            }
            $templateIdStr = (string) $templateId;

            $existing = DB::table('sku_faq_responses')
                ->where('sku_id', $skuIdStr)
                ->where('template_id', $templateIdStr)
                ->first();

            if ($existing) {
                DB::table('sku_faq_responses')
                    ->where('id', $existing->id)
                    ->update(['answer' => $answer, 'updated_at' => now()]);
            } else {
                DB::table('sku_faq_responses')->insert([
                    'id'         => (string) Str::uuid(),
                    'sku_id'     => $skuIdStr,
                    'template_id'=> $templateIdStr,
                    'answer'     => $answer,
                    'approved'   => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            AuditLog::create([
                'entity_type' => 'sku_faq_response',
                'entity_id'   => $skuIdStr,
                'action'      => 'faq_save',
                'field_name'  => 'faq_answer_template_' . $templateIdStr,
                'old_value'   => null,
                'new_value'   => $answer,
                'actor_id'    => (function_exists('auth') && auth()->check()) ? (string) auth()->id() : 'SYSTEM',
                'actor_role'  => (function_exists('auth') && auth()->check() && auth()->user() && auth()->user()->role) ? auth()->user()->role->name : 'system',
            ]);
        }
    }
}
