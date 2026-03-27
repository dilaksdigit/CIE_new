<?php
// SOURCE: openapi.yaml POST /brief/generate; CIE_Master_Developer_Build_Spec.docx §12.3 — decay brief payload
namespace App\Services;

use App\Models\AuditLog;
use App\Models\ContentBrief;
use App\Models\Sku;
use App\Support\BusinessRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BriefGenerationService
{
    /**
     * SOURCE: CIE_Master_Developer_Build_Spec.docx §6.5 / §12.3 — DECAY_REFRESH brief with full spec fields.
     *
     * @param list<string>       $failingQuestions question_id strings
     * @param array<string,mixed> $options optional: competitor_answers, ai_suggested_revision, current_answer_block
     */
    public function generateDecayRefreshBrief(string $skuId, array $failingQuestions, array $options = []): ContentBrief
    {
        $sku = Sku::findOrFail($skuId);
        $title = 'Decay Refresh: ' . ($sku->title ?: $sku->sku_code ?: $sku->id);

        // SOURCE: CIE_Master_Developer_Build_Spec.docx §12.3; CLAUDE.md §4 — no silent (int)null → 0 deadline.
        $deadlineRaw = BusinessRules::get('decay.auto_brief_deadline_days');
        if ($deadlineRaw === null || $deadlineRaw === '') {
            throw new \RuntimeException(
                'Missing required config: decay.auto_brief_deadline_days. Seed value 7 in business_rules table.'
            );
        }
        $deadlineDays = (int) $deadlineRaw;
        if ($deadlineDays < 1) {
            throw new \RuntimeException(
                'Invalid config: decay.auto_brief_deadline_days must be >= 1, got '.$deadlineDays
            );
        }
        $deadline = now()->addDays($deadlineDays)->toDateString();

        $latestRunId = null;
        if (Schema::hasTable('ai_audit_results')) {
            $latestRunId = DB::table('ai_audit_results as air')
                ->where('air.cited_sku_id', $skuId)
                ->orderByDesc('air.run_date')
                ->value('air.run_id');
        }

        $failingDetail = [];
        foreach ($failingQuestions as $qid) {
            $qidStr = (string) $qid;
            $engines = [];
            if ($latestRunId) {
                $engines = DB::table('ai_audit_results')
                    ->where('run_id', $latestRunId)
                    ->where('question_id', $qidStr)
                    ->where('score', 0)
                    ->where(function ($q) {
                        $q->whereNull('is_available')->orWhere('is_available', true);
                    })
                    ->pluck('engine')
                    ->unique()
                    ->values()
                    ->all();
            }
            $failingDetail[] = [
                'question_id' => $qidStr,
                'engines_scoring_zero' => $engines,
            ];
        }

        $goldenById = $this->loadGoldenQuestionsById($failingQuestions);

        $structuredCompetitors = [];
        if ($latestRunId && Schema::hasTable('ai_audit_results') && $failingQuestions !== []) {
            // SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §5.3 — excerpts scraped from AI responses where our SKU scored 0.
            $rows = DB::table('ai_audit_results')
                ->where('run_id', $latestRunId)
                ->where('cited_sku_id', $skuId)
                ->whereIn('question_id', array_map('strval', $failingQuestions))
                ->where('score', 0)
                ->whereNotNull('response_hash')
                ->where(function ($q) {
                    $q->whereNull('is_available')->orWhere('is_available', true);
                })
                ->orderBy('question_id')
                ->orderBy('engine')
                ->limit(3)
                ->get(['question_id', 'engine', 'response_hash']);
            foreach ($rows as $r) {
                $qid = (string) $r->question_id;
                $snippet = is_string($r->response_hash) ? $r->response_hash : '';
                $gq = $goldenById[$qid] ?? null;
                $qtext = $gq->question_text ?? $qid;
                $eng = (string) $r->engine;
                $structuredCompetitors[] = [
                    'question' => $qtext,
                    'engine' => $eng,
                    'competitor_mentioned' => $this->extractCompetitorCandidate($snippet),
                    'answer_excerpt' => mb_substr($snippet, 0, 500),
                    'source' => 'ai_engine_response',
                    'our_score' => 0,
                    'analysis' => sprintf(
                        'On "%s", %s did not cite our product. Engine output (excerpt): "%s..."',
                        $qtext,
                        $eng,
                        mb_substr(trim($snippet), 0, 100)
                    ),
                ];
            }
        }

        if (!empty($options['competitor_answers']) && is_array($options['competitor_answers'])) {
            $structuredCompetitors = [];
            foreach ($options['competitor_answers'] as $entry) {
                if (is_string($entry)) {
                    $structuredCompetitors[] = [
                        'question' => '',
                        'engine' => '',
                        'competitor_mentioned' => null,
                        'answer_excerpt' => mb_substr($entry, 0, 300),
                        'source' => 'ai_engine_response',
                    ];
                }
            }
        }

        $firstQ = isset($failingQuestions[0]) ? (string) $failingQuestions[0] : '';
        $firstGolden = $firstQ !== '' ? ($goldenById[$firstQ] ?? null) : null;

        $answerBlock = (isset($options['current_answer_block']) && is_string($options['current_answer_block']))
            ? $options['current_answer_block']
            : (string) ($sku->ai_answer_block ?? $sku->answer_block ?? '');

        $revision = isset($options['ai_suggested_revision']) && is_string($options['ai_suggested_revision']) && $options['ai_suggested_revision'] !== ''
            ? $options['ai_suggested_revision']
            : $this->buildDefaultRevisionText($sku, $firstGolden, $firstQ, $answerBlock);

        $tierVal = $sku->getAttribute('tier');
        if (is_object($tierVal) && method_exists($tierVal, 'value')) {
            $tierVal = $tierVal->value;
        }

        $suggestedActions = [
            'sku' => [
                'sku_id' => (string) $sku->id,
                'sku_name' => (string) ($sku->title ?? ''),
                'tier' => (string) $tierVal,
                'margin_percent' => $sku->margin_percent !== null ? (float) $sku->margin_percent : null,
            ],
            'failing_audit_questions' => $failingDetail,
            'current_answer_block' => $answerBlock,
            'competitor_answers' => $structuredCompetitors !== [] ? $structuredCompetitors : null,
            'top_competitor_answers' => $structuredCompetitors !== []
                ? array_values(array_filter(array_map(fn ($c) => $c['answer_excerpt'] ?? null, $structuredCompetitors)))
                : null,
            'ai_suggested_revision' => $revision,
            'deadline' => $deadline,
            'success_criteria' => 'Citation score >= 1 on next weekly AI audit for all listed questions.',
        ];

        $brief = ContentBrief::create([
            'sku_id' => $sku->id,
            'brief_type' => 'DECAY_REFRESH',
            'priority' => 'HIGH',
            'title' => $title,
            'description' => $revision,
            'current_content' => $answerBlock,
            'suggested_actions' => $suggestedActions,
            'status' => 'open',
            'deadline' => $deadline,
        ]);

        try {
            AuditLog::create([
                'entity_type' => 'brief',
                'entity_id' => $sku->id,
                'action' => 'brief_generated',
                'field_name' => null,
                'old_value' => null,
                'new_value' => 'auto_decay_brief',
                'actor_id' => (function_exists('auth') && app()->bound('auth') && auth()->check()) ? (string) auth()->id() : 'SYSTEM',
                'actor_role' => (function_exists('auth') && app()->bound('auth') && auth()->check())
                    ? (string) (optional(auth()->user()->role)->name ?? 'system')
                    : 'system',
                'timestamp' => now(),
                'user_id' => (function_exists('auth') && app()->bound('auth') && auth()->check()) ? auth()->id() : null,
                'ip_address' => request() ? request()->ip() : null,
                'user_agent' => request() ? request()->userAgent() : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Fail-soft if audit_log schema differs
        }

        return $brief;
    }

    /**
     * @param  list<string>  $failingQuestions
     * @return array<string, object>
     */
    private function loadGoldenQuestionsById(array $failingQuestions): array
    {
        if ($failingQuestions === [] || !Schema::hasTable('ai_golden_queries')) {
            return [];
        }
        $ids = array_values(array_filter(array_map('strval', $failingQuestions)));
        $q = DB::table('ai_golden_queries as g')->whereIn('g.question_id', $ids);
        if (Schema::hasTable('intent_taxonomy') && Schema::hasColumn('ai_golden_queries', 'intent_type')) {
            $q->leftJoin('intent_taxonomy as it', 'g.intent_type', '=', 'it.intent_id')
                ->select('g.question_id', 'g.question_text', 'g.query_family', 'it.label as intent_label', 'it.intent_key as intent_key');
        } else {
            $q->select('g.question_id', 'g.question_text', 'g.query_family');
        }
        $out = [];
        foreach ($q->get() as $row) {
            $out[(string) $row->question_id] = $row;
        }

        return $out;
    }

    private function extractCompetitorCandidate(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        if (preg_match('/\b([A-Z][a-z0-9]+(?:\s+[A-Z][a-z0-9]+)+)\b/u', $text, $m)) {
            return $m[1];
        }
        if (preg_match('/(?:such as|like|including|recommends?)\s+([^,.;]{3,100})/i', $text, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §12.3 — one-line intent-specific revision hint (intent taxonomy keys).
     */
    private function intentSpecificGuidance(string $intentKey): string
    {
        $k = strtolower(trim($intentKey));
        return match (true) {
            $k === 'compatibility' => 'compatible models, fitting types, and wattage limits',
            $k === 'installation' => 'step-by-step install, required tools, and safety certifications',
            $k === 'specification' => 'dimensions, materials, IP rating, and weight',
            $k === 'problem_solving' => 'the specific problem solved and a measurable benefit',
            $k === 'comparison' => 'clear differentiators versus alternatives buyers compare',
            $k === 'replacement' => 'direct replacement compatibility and sizing checks',
            $k === 'inspiration' => 'style, finish, and room-use context that matches the query',
            str_contains($k, 'safety') || str_contains($k, 'regulatory') => 'certifications, codes, and safe-use constraints',
            default => 'specific product attributes that directly answer this question',
        };
    }

    private function suggestSpecificAddition(?string $intentLabel, ?string $queryFamily): string
    {
        $l = strtolower((string) $intentLabel);
        if (str_contains($l, 'compat')) {
            return 'compatible fitting types, wattage limits, and applicable standards';
        }
        if (str_contains($l, 'install')) {
            return 'installation steps, required tools, and safety certifications';
        }
        if (str_contains($l, 'specification')) {
            return 'dimensions, materials, and technical ratings (IP rating, lumens, voltage)';
        }
        if (str_contains($l, 'comparison')) {
            return 'clear differentiators versus alternatives buyers compare';
        }
        if (str_contains($l, 'problem') || str_contains($l, 'troubleshoot')) {
            return 'symptom-to-fix guidance and when professional help is needed';
        }
        if (str_contains($l, 'replacement')) {
            return 'direct replacement compatibility and sizing checks';
        }
        if (str_contains($l, 'safety') || str_contains($l, 'regulatory')) {
            return 'certifications, codes, and safe-use constraints';
        }
        if (str_contains($l, 'inspiration')) {
            return 'style, finish, and room-use context that matches the query';
        }
        $f = strtolower((string) $queryFamily);
        if ($f === 'primary') {
            return 'a direct answer to the primary buyer question in the opening lines';
        }
        if ($f === 'secondary') {
            return 'supporting proof points, comparisons, and objections handled in-body';
        }

        return 'clear specifications, compatibility, and use-case clarity tied to the failing audit questions';
    }

    private function buildDefaultRevisionText(Sku $sku, ?object $firstGolden, string $firstQuestionId, string $answerBlock): string
    {
        $productName = (string) ($sku->title ?: $sku->sku_code ?: $sku->id);
        $qtext = $firstGolden && isset($firstGolden->question_text)
            ? (string) $firstGolden->question_text
            : ($firstQuestionId !== '' ? $firstQuestionId : 'the failing golden query');
        $ab = trim($answerBlock);
        $excerpt = mb_substr($ab, 0, 80);
        $ellipsis = mb_strlen($ab) > 80 ? '...' : '';
        $intentKey = $firstGolden && isset($firstGolden->intent_key) ? (string) $firstGolden->intent_key : '';
        $intentLabel = $firstGolden && isset($firstGolden->intent_label) ? (string) $firstGolden->intent_label : 'this intent';
        $guidance = $this->intentSpecificGuidance($intentKey);

        return sprintf(
            'Answer block for "%s" (%s) does not address "%s". Current block (%d chars): "%s%s" — '.
            'AI engines citing competitors who cover %s. Add: %s.',
            $productName,
            (string) $sku->id,
            $qtext,
            mb_strlen($ab),
            $excerpt,
            $ellipsis,
            $intentLabel,
            $guidance
        );
    }
}
