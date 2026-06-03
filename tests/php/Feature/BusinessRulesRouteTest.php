<?php
// SOURCE: UI-01 — BusinessRules admin page must be routed

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class BusinessRulesRouteTest extends TestCase
{
    /** @test App.jsx exposes /admin/business-rules route */
    public function test_app_jsx_has_business_rules_route(): void
    {
        $app = dirname(__DIR__, 3) . '/frontend/src/App.jsx';
        $contents = file_get_contents($app);
        $this->assertStringContainsString('/admin/business-rules', $contents);
        $this->assertStringContainsString('BusinessRules', $contents);
    }

    /** @test admin sidebar links to Business Rules page */
    public function test_sidebar_has_business_rules_nav_item(): void
    {
        $sidebar = dirname(__DIR__, 3) . '/frontend/src/components/common/Sidebar.jsx';
        $contents = file_get_contents($sidebar);
        $this->assertStringContainsString('/admin/business-rules', $contents);
        $this->assertStringContainsString('Business Rules', $contents);
    }
}
