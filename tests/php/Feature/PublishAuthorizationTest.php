<?php
// SOURCE: Production Roadmap — publish authorization fix (DECISION-002/010)

namespace Tests\Feature;

use App\Enums\TierType;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use PHPUnit\Framework\TestCase;

class PublishAuthorizationTest extends TestCase
{
    private PermissionService $permissions;

    protected function setUp(): void
    {
        $this->permissions = new PermissionService();
    }

    /** @test content_editor (primary writer) may publish non-kill SKUs */
    public function test_content_editor_can_publish_non_kill_sku(): void
    {
        $user = $this->userWithRole('CONTENT_EDITOR');
        $sku = (object) ['tier' => TierType::HERO];
        $this->assertTrue($this->permissions->canPublishSku($user, $sku));
    }

    /** @test kill-tier SKUs cannot be published regardless of role */
    public function test_kill_tier_blocked_from_publish(): void
    {
        $user = $this->userWithRole('CONTENT_EDITOR');
        $sku = (object) ['tier' => TierType::KILL];
        $this->assertFalse($this->permissions->canPublishSku($user, $sku));
    }

    /** @test publish route must allow writer roles (not CONTENT_LEAD-only) */
    public function test_publish_route_includes_content_editor(): void
    {
        $routesFile = dirname(__DIR__, 3) . '/backend/php/routes/api.php';
        $this->assertFileExists($routesFile);
        $contents = file_get_contents($routesFile);
        $this->assertMatchesRegularExpression(
            "/publish.*middleware\\('rbac:[^']*CONTENT_EDITOR/",
            $contents,
            'Publish route must include CONTENT_EDITOR for primary writer user'
        );
    }

    /** @test publish handler uses PermissionService not User::can() */
    public function test_publish_controller_uses_permission_service(): void
    {
        $controllerFile = dirname(__DIR__, 3) . '/backend/php/src/Controllers/SkuController.php';
        $contents = file_get_contents($controllerFile);
        $this->assertStringContainsString('$this->permissionService->canPublishSku', $contents);
        $this->assertStringNotContainsString("->can('publish_sku')", $contents);
    }

    private function userWithRole(string $roleName): User
    {
        $role = new Role(['name' => $roleName]);
        $user = new User();
        $user->setRelation('roles', collect([$role]));
        return $user;
    }
}
