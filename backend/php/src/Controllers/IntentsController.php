<?php

namespace App\Controllers;

use App\Models\IntentTaxonomy;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;

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
            $query->whereRaw('JSON_CONTAINS(tier_access, ?)', [json_encode($tier)]);
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
        $tierRules = [
            'hero' => ['max_secondary' => 3, 'all_intents' => true],
            'support' => ['max_secondary' => 2, 'all_intents' => true],
            'harvest' => ['max_secondary' => 1, 'allowed_intents' => [1, 3, 4]], // problem_solving, compatibility, specification
            'kill' => ['max_secondary' => 0, 'all_intents' => false],
        ];
        return response()->json(['data' => ['intents' => $intents, 'tier_rules' => $tierRules]]);
    }
}
