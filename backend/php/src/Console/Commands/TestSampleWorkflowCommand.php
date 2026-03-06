<?php

namespace App\Console\Commands;

use App\Models\Sku;
use App\Models\Cluster;
use App\Models\Intent;
use App\Models\SkuIntent;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * One-off workflow test using sample SKU data.
 * Creates cluster + SKU + sku_intents, runs validation, checks audit_log.
 */
class TestSampleWorkflowCommand extends Command
{
    protected $signature = 'cie:test-sample-workflow {--json= : Path to sample JSON file, or use embedded first SKU}';
    protected $description = 'Run workflow once with sample data: create SKU, validate, verify audit_log';

    public function handle(ValidationService $validationService): int
    {
        $jsonPath = $this->option('json');
        if ($jsonPath && is_file($jsonPath)) {
            $content = file_get_contents($jsonPath);
            $items = json_decode($content, true);
        } else {
            $items = $this->embeddedSample();
        }

        if (empty($items) || !is_array($items)) {
            $this->error('No sample items (provide --json=path or use embedded).');
            return 1;
        }

        $sample = is_array($items[0]) ? $items[0] : $items;
        $this->info('Using sample SKU: ' . ($sample['sku_code'] ?? 'unknown'));

        $user = User::first();
        if ($user) {
            auth()->login($user);
        }

        $cluster = $this->ensureCluster($sample);
        if (!$cluster) {
            return 1;
        }

        $sku = $this->createSkuFromSample($sample, $cluster);
        if (!$sku) {
            return 1;
        }

        $this->attachIntents($sample, $sku, $cluster);
        $sku->refresh();
        $sku->load(['primaryCluster', 'skuIntents.intent']);

        $this->info('Running validation...');
        $result = $validationService->validate($sku);
        $valid = $result['valid'] ?? false;
        $status = $result['status'] ?? null;
        $results = $result['results'] ?? [];

        $this->table(
            ['Gate', 'Passed', 'Blocking', 'Reason'],
            array_map(fn ($r) => [
                $r['gate'] ?? '-',
                ($r['passed'] ?? false) ? 'yes' : 'no',
                ($r['blocking'] ?? false) ? 'yes' : 'no',
                substr($r['reason'] ?? '', 0, 60),
            ], $results)
        );
        $this->line('Overall: ' . ($valid ? 'VALID' : 'INVALID') . ' (status: ' . ($status ? $status->value ?? $status : '') . ')');

        $expected = $sample['expected_outputs']['gate_results'] ?? null;
        if ($expected) {
            $expOverall = $expected['overall'] ?? '';
            $this->line('Expected overall: ' . $expOverall);
        }

        $lastAudit = AuditLog::orderByDesc('created_at')->first();
        if ($lastAudit) {
            $hasActor = !empty($lastAudit->actor_id) && !empty($lastAudit->actor_role);
            $hasTimestamp = Schema::hasColumn('audit_log', 'timestamp') ? !empty($lastAudit->timestamp) : false;
            $this->line('');
            $this->line('Latest audit_log row: entity_type=' . ($lastAudit->entity_type ?? '') . ', action=' . ($lastAudit->action ?? ''));
            $this->line('Canonical fields: actor_id=' . ($hasActor ? 'set' : 'MISSING') . ', timestamp=' . ($hasTimestamp ? 'set' : 'MISSING'));
            if (!$hasActor || !$hasTimestamp) {
                $this->warn('Run migration 035 to backfill actor_id/actor_role/timestamp and add NOT NULL.');
            }
        }

        $this->info('Workflow test done. SKU id: ' . $sku->id);
        return 0;
    }

    private function embeddedSample(): array
    {
        $first = [
            'sku_code' => 'CBL-WHT-2C-3M',
            'product_name' => 'White Round Flex Cable 2-Core 3m',
            'tier' => 'Support',
            'category' => 'Cables',
            'identity' => [
                'product_class' => 'Electrical Cable',
                'product_type' => 'Extension Flex',
                'material_primary' => 'PVC',
                'colour' => 'White',
                'core_count' => 2,
                'style' => 'Round',
                'length_m' => 3.0,
                'fitting_type' => 'Bare ends',
                'certifications' => ['CE'],
                'weight_kg' => 0.35,
                'ip_rating' => null,
            ],
            'use_case' => [
                'cluster_id' => 'CLU-CBL-EXT',
                'cluster_intent' => 'Extend or replace existing lamp cable safely',
                'primary_intent' => 'Specification',
                'secondary_intents' => ['Compatibility'],
                'best_for' => [
                    'Table lamp rewiring',
                    'Floor lamp cable extension',
                ],
                'not_for' => [
                    'Ceiling pendant installations (needs 3-core)',
                    'Outdoor use',
                ],
                'comparison_anchors' => [],
            ],
            'commercial' => [
                'contribution_margin_pct' => 44.0,
                'cppc' => 0.42,
                'velocity_90d' => 312,
                'return_rate_pct' => 3.5,
                'composite_score' => 62.1,
                'price_gbp' => 6.99,
                'cost_gbp' => 3.91,
            ],
            'content' => [
                'shopify_title' => 'Replacement Flex Cable for Table and Floor Lamps | 2-Core White PVC 3m',
                'feed_title' => 'White 2-Core Round Flex Cable 3m for Table Lamp and Floor Lamp Rewiring',
                'meta_description' => 'White 2-core round flex cable, 3m length. Ideal for rewiring table lamps and floor lamps. CE marked. Bare ends for custom wiring.',
                'ai_answer_block' => 'A 2-core white PVC flex cable at 3m length provides enough reach for most table lamp and floor lamp rewiring projects. Bare ends allow custom termination with your existing plug and lamp holder. CE marked for indoor domestic use.',
                'ai_answer_block_chars' => 271,
                'ppc_headlines' => [
                    'Lamp Rewiring Cable 3m',
                    'Table Lamp Flex Replacement',
                ],
                'alt_text' => 'White 2-core round flex cable 3m for table and floor lamp rewiring',
                'quotable_facts' => [
                    '3m length covers most floor lamp cable runs',
                    '2-core suitable for double-insulated Class II lamps',
                ],
            ],
            'authority' => [
                'expert_statement' => 'CE marked. Suitable for Class II (double insulated) luminaires only. Not for earthed fittings.',
                'wikidata_entities' => [
                    [
                        'qid' => 'Q174102',
                        'label' => 'Electrical cable',
                    ],
                ],
                'certifications_detail' => ['CE marked'],
            ],
            'faqs' => [
                [
                    'question' => 'Is this cable suitable for a ceiling pendant?',
                    'answer' => 'No, ceiling pendants require a 3-core earthed cable. This 2-core cable is for double-insulated table and floor lamps only.',
                    'intent_tag' => 'Compatibility',
                ],
            ],
            'expected_outputs' => [
                'gate_results' => [
                    'G1' => 'PASS',
                    'G2' => 'PASS',
                    'G3' => 'PASS',
                    'G4' => 'PASS',
                    'G5' => 'PASS',
                    'G6' => 'PASS',
                    'G7' => 'PASS',
                    'overall' => 'ALL_PASS',
                ],
            ],
        ];
        return [$first];
    }

    private function ensureCluster(array $sample): ?Cluster
    {
        $uc = $sample['use_case'] ?? [];
        $clusterId = $uc['cluster_id'] ?? 'CLU-CBL-P-E27';
        $intentStatement = $uc['cluster_intent'] ?? 'Connect and power a pendant light safely and stylishly';
        $primaryIntentName = $uc['primary_intent'] ?? 'Compatibility';

        $intent = Intent::whereRaw('LOWER(name) = ?', [strtolower(str_replace([' ', '-', '/'], ['_', '_', '_'], $primaryIntentName))])
            ->orWhere('display_name', $primaryIntentName)
            ->orWhere('name', 'compatibility')
            ->first();
        if (!$intent) {
            $intent = Intent::first();
        }
        if (!$intent) {
            $this->error('No intents in DB. Run seed 001_seed_intents.sql');
            return null;
        }

        $cluster = Cluster::where('name', $clusterId)->first();
        if (!$cluster) {
            $cluster = Cluster::create([
                'name' => $clusterId,
                'intent_statement' => $intentStatement,
                'primary_intent_id' => $intent->id,
            ]);
            $this->line('Created cluster: ' . $cluster->id);
        }
        return $cluster;
    }

    private function createSkuFromSample(array $sample, Cluster $cluster): ?Sku
    {
        $uc = $sample['use_case'] ?? [];
        $content = $sample['content'] ?? [];
        $auth = $sample['authority'] ?? [];
        $tier = $sample['tier'] ?? 'Support';
        $tierUpper = strtoupper($tier);

        $shortDesc = $content['meta_description'] ?? $content['ai_answer_block'] ?? 'Sample product for CIE workflow test. Safe wiring and compatibility with LED and CFL.';
        if (strlen($shortDesc) < 50) {
            $shortDesc = str_pad($shortDesc, 51, ' ');
        }

        $data = [
            'sku_code' => $sample['sku_code'] ?? ('TEST-' . uniqid()),
            'title' => $content['shopify_title'] ?? $sample['product_name'] ?? 'Test SKU',
            'tier' => $tierUpper,
            'primary_cluster_id' => $cluster->id,
            'short_description' => $shortDesc,
            'long_description' => $content['ai_answer_block'] ?? $shortDesc,
            'best_for' => is_array($uc['best_for'] ?? null) ? json_encode($uc['best_for']) : null,
            'not_for' => is_array($uc['not_for'] ?? null) ? json_encode($uc['not_for']) : null,
        ];

        if (Schema::hasColumn('skus', 'ai_answer_block') && array_key_exists('ai_answer_block', $content)) {
            $data['ai_answer_block'] = $content['ai_answer_block'];
        }
        if (Schema::hasColumn('skus', 'expert_authority') && !empty($auth['expert_statement'])) {
            $data['expert_authority'] = $auth['expert_statement'];
        }

        $sku = Sku::create($data);
        $this->line('Created SKU: ' . $sku->id . ' (' . $sku->sku_code . ')');
        return $sku;
    }

    private function attachIntents(array $sample, Sku $sku, Cluster $cluster): void
    {
        $uc = $sample['use_case'] ?? [];
        $primaryName = $uc['primary_intent'] ?? 'Compatibility';
        $secondaryNames = $uc['secondary_intents'] ?? [];

        $map = [
            'Compatibility'     => 'compatibility',
            'Comparison'        => 'comparison',
            'Problem-Solving'    => 'problem_solving',
            'Inspiration'       => 'inspiration',
            'Specification'     => 'specification',
            'Installation'      => 'installation',
            'Safety/Compliance' => 'safety_compliance',
            'Replacement'      => 'replacement',
            'Bulk/Trade'       => 'bulk_trade',
        ];

        $primaryKey = $map[$primaryName] ?? 'compatibility';
        $intent = Intent::where('name', $primaryKey)->first() ?? Intent::first();
        if ($intent) {
            SkuIntent::create([
                'sku_id' => $sku->id,
                'intent_id' => $intent->id,
                'cluster_id' => $cluster->id,
                'is_primary' => true,
            ]);
        }

        foreach (array_slice($secondaryNames, 0, 2) as $ord => $name) {
            $key = $map[$name] ?? strtolower(str_replace([' ', '-', '/'], '_', $name));
            $sec = Intent::where('name', $key)->first();
            if ($sec) {
                SkuIntent::create([
                    'sku_id' => $sku->id,
                    'intent_id' => $sec->id,
                    'cluster_id' => $cluster->id,
                    'is_primary' => false,
                ]);
            }
        }
    }
}
