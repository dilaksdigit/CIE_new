<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx Section 17 Phase 0.1 / 0.5

namespace Tests\Phase0;

use PHPUnit\Framework\TestCase;

/**
 * Phase 0.1 — business_rules_audit UPDATE must raise a SIGNAL error.
 * Phase 0.5 — audit_log UPDATE/DELETE must be blocked.
 * SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 0.1 + 0.5
 */
class AuditLogImmutabilityTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO(
            sprintf('mysql:host=%s;dbname=%s', getenv('DB_HOST'), getenv('DB_DATABASE')),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD')
        );
    }

    /** @test Phase 0.1 — UPDATE on business_rules_audit must fail with SIGNAL */
    public function test_business_rules_audit_update_is_blocked(): void
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 0.1
        // "Attempt UPDATE on audit row — must fail."
        $this->expectException(\PDOException::class);
        $this->pdo->exec("UPDATE business_rules_audit SET new_value = 'tampered' LIMIT 1");
    }

    /** @test Phase 0.5 — DELETE on audit_log must fail */
    public function test_audit_log_delete_is_blocked(): void
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 0.5
        // "Attempt DELETE on log row — must fail."
        $this->expectException(\PDOException::class);
        $this->pdo->exec("DELETE FROM audit_log LIMIT 1");
    }

    /** @test Phase 0.5 — UPDATE on audit_log must fail */
    public function test_audit_log_update_is_blocked(): void
    {
        // SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 0.5
        $this->expectException(\PDOException::class);
        $this->pdo->exec("UPDATE audit_log SET action = 'tampered' LIMIT 1");
    }
}
