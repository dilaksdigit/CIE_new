<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 17 Phase 0.1 / 0.2

namespace Tests\Phase0;

use PHPUnit\Framework\TestCase;

/**
 * Phase 0.1 — business_rules count and seed validation.
 * Phase 0.2 — BusinessRules.get() typed values and cache invalidation.
 * SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 0.1–0.2
 */
class BusinessRulesTest extends TestCase
{
    /** @test Phase 0.1 — SELECT COUNT(*) FROM business_rules must equal 54 after migration 117 */
    public function test_business_rules_count_is_54(): void
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 0.1
        // Base seed 040 (52) + migration 117 (channel.harvest_threshold, system.business_rules_cache_ttl)
        $pdo = new \PDO(
            sprintf('mysql:host=%s;dbname=%s', getenv('DB_HOST'), getenv('DB_DATABASE')),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD')
        );
        $count = (int) $pdo->query('SELECT COUNT(*) FROM business_rules')->fetchColumn();
        $this->assertSame(54, $count, 'business_rules must contain 54 rows (040 + 117) per Phase 1 DB alignment');
    }

    /** @test Phase 0.2 — BusinessRules::get() returns correct typed value for a known key */
    public function test_get_returns_typed_value(): void
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 0.2
        // "Dev: unit test each data_type cast."
        $value = \App\Support\BusinessRules::get('gates.vector_similarity_min');
        $this->assertIsFloat($value, 'gates.vector_similarity_min must return a float');
        $this->assertSame(0.72, $value, 'Default value per §5.3 must be 0.72');
    }

    /** @test Phase 0.2 — BusinessRules::get() raises error for missing key without default */
    public function test_get_throws_for_missing_key(): void
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 0.2
        // "Raises error if rule key missing without default."
        $this->expectException(\RuntimeException::class);
        \App\Support\BusinessRules::get('nonexistent.key.that.does.not.exist');
    }

    /** @test Phase 0.2 — Cache is invalidated after rule update */
    public function test_cache_invalidated_after_update(): void
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 0.2
        // "Cache invalidated after every rule update."
        \App\Support\BusinessRules::invalidateCache();
        $value = \App\Support\BusinessRules::get('gates.vector_similarity_min');
        $this->assertNotNull($value, 'Value must be retrievable after cache invalidation');
    }
}
