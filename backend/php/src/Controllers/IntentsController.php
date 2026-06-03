<?php

namespace App\Controllers;

use App\Models\IntentTaxonomy;
use App\Support\BusinessRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IntentsController
{
    /**
     * GET /api/v1/taxonomy/intents — locked 9-intent taxonomy, optionally filtered by tier. Unified API 7.1.
     */
    public function index(Request $request)
    {
        $query = IntentTaxonomy::orderBy('intent_id');
        $tier = $request->query('tier');
        if (in_array($tier, ['hero', 'support', 'harvest', 'kill'], true)) {
            $query->whereJsonContains('tier_access', $tier);
        }
        $intents = $query->get()->map(function ($row) {
            return [
                'intent_id' => $row->intent_id,
                'intent_key' => $row->intent_key,
                'label' => $row->label,
                'definition' => $row->definition ?? null,
                'tier_access' => json_decode($row->tier_access ?? '[]', true),
            ];
        });

        return response()->json(['data' => ['intents' => $intents, 'tier_rules' => $this->buildTierRules()]]);
    }

    /**
     * Tier rules aligned with Python _load_tier_rules_dict (tier_intent_rules + BusinessRules).
     */
    private function buildTierRules(): array
    {
        $harvestIds = [];
        if (Schema::hasTable('tier_intent_rules')) {
            $harvestIds = DB::table('tier_intent_rules')
                ->whereRaw('LOWER(TRIM(tier)) = ?', ['harvest'])
                ->pluck('intent_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();
        }
        if ($harvestIds === []) {
            // SOURCE: database/seeds/007 — harvest intent_id 1,3,4 when DB not seeded (tests)
            $harvestIds = [1, 3, 4];
        }

        return [
            'hero' => [
                'max_secondary' => (int) BusinessRules::get('gates.hero_max_secondary', 3),
                'all_intents' => true,
            ],
            'support' => [
                'max_secondary' => (int) BusinessRules::get('gates.support_max_secondary', 2),
                'all_intents' => true,
            ],
            'harvest' => [
                'max_secondary' => (int) BusinessRules::get('gates.harvest_max_secondary', 1),
                'allowed_intents' => $harvestIds,
            ],
            'kill' => [
                'max_secondary' => 0,
                'all_intents' => false,
            ],
        ];
    }
}
