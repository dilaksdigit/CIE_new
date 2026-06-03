<?php
// SOURCE: PHP taxonomy tier_rules aligned with Python _load_tier_rules_dict

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class IntentsControllerTierRulesTest extends TestCase
{
    /** @test IntentsController loads tier rules from DB + BusinessRules not hardcoded array */
    public function test_intents_controller_builds_tier_rules_from_db_and_business_rules(): void
    {
        $file = dirname(__DIR__, 3) . '/backend/php/src/Controllers/IntentsController.php';
        $contents = file_get_contents($file);
        $this->assertStringContainsString('buildTierRules', $contents);
        $this->assertStringContainsString('tier_intent_rules', $contents);
        $this->assertStringContainsString("BusinessRules::get('gates.hero_max_secondary'", $contents);
        $this->assertStringContainsString("BusinessRules::get('gates.harvest_max_secondary'", $contents);
        $this->assertStringNotContainsString("'harvest' => ['max_secondary' => 1, 'allowed_intents' => [1, 3, 4]", $contents);
    }
}
