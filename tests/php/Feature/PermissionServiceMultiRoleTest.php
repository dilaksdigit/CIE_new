<?php
// SOURCE: Multi-role seed users (writer = EDITOR + SPECIALIST; reviewer = LEAD + SEO)

namespace Tests\Feature;

use App\Enums\TierType;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use PHPUnit\Framework\TestCase;

class PermissionServiceMultiRoleTest extends TestCase
{
    private PermissionService $permissions;

    protected function setUp(): void
    {
        $this->permissions = new PermissionService();
    }

    /** @test writer seed roles: both editor and specialist can publish */
    public function test_writer_multi_role_can_publish(): void
    {
        $user = $this->userWithRoles(['CONTENT_EDITOR', 'PRODUCT_SPECIALIST']);
        $sku = (object) ['tier' => TierType::HERO];
        $this->assertTrue($this->permissions->canPublishSku($user, $sku));
        $this->assertTrue($this->permissions->canEditContentFields($user));
    }

    /** @test reviewer with lead + seo can publish but is not content-only lead */
    public function test_reviewer_multi_role_can_publish_and_not_only_publish(): void
    {
        $user = $this->userWithRoles(['CONTENT_LEAD', 'SEO_GOVERNOR']);
        $sku = (object) ['tier' => TierType::SUPPORT];
        $this->assertTrue($this->permissions->canPublishSku($user, $sku));
        $this->assertFalse($this->permissions->canOnlyPublish($user));
    }

    /** @test content lead only (no editor) is publish-only */
    public function test_content_lead_only_publish_mode(): void
    {
        $user = $this->userWithRoles(['CONTENT_LEAD']);
        $this->assertTrue($this->permissions->canOnlyPublish($user));
        $this->assertFalse($this->permissions->canEditContentFields($user));
    }

    private function userWithRoles(array $roleNames): User
    {
        $roles = collect(array_map(fn ($name) => new Role(['name' => $name]), $roleNames));
        $user = new User();
        $user->setRelation('roles', $roles);
        return $user;
    }
}
