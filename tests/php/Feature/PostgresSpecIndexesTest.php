<?php
// SOURCE: DECISION-014 — greenfield PG must apply canonical indexes, not converter-only.

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class PostgresSpecIndexesTest extends TestCase
{
    public function test_docker_compose_mounts_canonical_indexes(): void
    {
        $yml = file_get_contents(dirname(__DIR__, 3) . '/docker-compose.yml');
        $this->assertStringContainsString('database/postgres/canonical', $yml);
        $this->assertStringContainsString('docker-entrypoint-canonical', $yml);
    }

    public function test_init_includes_spec_indexes_after_migrations(): void
    {
        $init = file_get_contents(dirname(__DIR__, 3) . '/database/postgres/init/03_spec_indexes.sql');
        $this->assertStringContainsString('03_spec_indexes.sql', $init);
        $canonical = file_get_contents(
            dirname(__DIR__, 3) . '/database/postgres/canonical/03_spec_indexes.sql'
        );
        $this->assertStringContainsString('CREATE INDEX IF NOT EXISTS', $canonical);
        $this->assertStringContainsString('idx_retry_status', $canonical);
    }

    public function test_converter_documents_deprecation(): void
    {
        $script = file_get_contents(
            dirname(__DIR__, 3) . '/scripts/convert_mysql_migrations_to_pg.py'
        );
        $this->assertStringContainsString('DEPRECATED', $script);
        $this->assertStringContainsString('generate_pg_spec_indexes', $script);
    }
}
