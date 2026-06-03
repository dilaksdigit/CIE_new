<?php
// SOURCE: Semrush greenfield fix — migration 148 must not drop table; 166 ensures it exists

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class SemrushMigrationTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        $this->repoRoot = dirname(__DIR__, 3);
    }

    /** @test migration 148 is a no-op (does not DROP semrush_imports) */
    public function test_migration_148_does_not_drop_semrush_imports(): void
    {
        foreach (['database/migrations', 'database/postgres/migrations'] as $dir) {
            $path = $this->repoRoot . '/' . $dir . '/148_drop_semrush_imports_table.sql';
            $this->assertFileExists($path, $path);
            $sql = file_get_contents($path);
            $this->assertStringNotContainsString('DROP TABLE', strtoupper($sql), $path);
        }
    }

    /** @test migration 166 creates semrush_imports for greenfield init */
    public function test_migration_166_ensures_semrush_imports_table(): void
    {
        $pg = $this->repoRoot . '/database/postgres/migrations/166_ensure_semrush_imports_table.sql';
        $mysql = $this->repoRoot . '/database/migrations/166_ensure_semrush_imports_table.sql';
        $this->assertFileExists($pg);
        $this->assertFileExists($mysql);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS semrush_imports', file_get_contents($pg));
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS semrush_imports', file_get_contents($mysql));
    }

    /** @test postgres bootstrap includes migration 166 */
    public function test_postgres_bootstrap_includes_migration_166(): void
    {
        $bootstrap = $this->repoRoot . '/database/postgres/init/01_migrations_bootstrap.sql';
        $this->assertStringContainsString('166_ensure_semrush_imports_table.sql', file_get_contents($bootstrap));
    }
}
