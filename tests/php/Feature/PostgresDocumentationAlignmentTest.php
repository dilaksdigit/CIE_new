<?php
// SOURCE: DECISION-013 — operator docs must not instruct MySQL misconfiguration.

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class PostgresDocumentationAlignmentTest extends TestCase
{
    public function test_quick_start_uses_psql_not_mysql_service(): void
    {
        $text = file_get_contents(dirname(__DIR__, 3) . '/QUICK_START_GUIDE.md');
        $this->assertStringNotContainsString('mysql-service', strtolower($text));
        $this->assertStringContainsString('database/postgres', $text);
    }

    public function test_implementation_guide_documents_pgsql(): void
    {
        $text = file_get_contents(dirname(__DIR__, 3) . '/IMPLEMENTATION_GUIDE.md');
        $this->assertStringContainsString('DECISION-013', $text);
        $this->assertStringContainsString('DB_CONNECTION=pgsql', $text);
        $this->assertStringNotContainsString('DB_PORT=3306', $text);
        $this->assertStringNotContainsString('pymysql==', $text);
    }

    public function test_env_example_uses_pgsql(): void
    {
        $text = file_get_contents(dirname(__DIR__, 3) . '/.env.example');
        $this->assertStringContainsString('DB_CONNECTION=pgsql', $text);
        $this->assertStringContainsString('DB_PORT=5432', $text);
    }

    public function test_verify_docs_script_exists(): void
    {
        $path = dirname(__DIR__, 3) . '/scripts/verify_docs_postgres_alignment.py';
        $this->assertFileExists($path);
    }
}
