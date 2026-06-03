<?php
// SOURCE: P3 — N8N/Shopify deploy wire test (mocked HTTP, no live Shopify)

namespace Tests\Feature;

use App\Models\Sku;
use App\Services\ChannelDeployService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ChannelDeployE2eTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        $this->repoRoot = dirname(__DIR__, 3);
    }

    /** @test Hero golden fields produce full N8N deploy payload */
    public function test_build_deploy_payload_includes_required_keys(): void
    {
        $golden = json_decode(
            file_get_contents($this->repoRoot . '/database/seeds/golden_test_data.json'),
            true
        );
        $row = $golden[0];
        $content = $row['content'] ?? [];
        $use = $row['use_case'] ?? [];

        $sku = new Sku([
            'id' => 'golden-hero-cbl-blk',
            'sku_code' => $row['sku_code'],
            'shopify_product_id' => $row['sku_code'],
            'title' => $content['shopify_title'] ?? '',
            'meta_title' => $content['shopify_title'] ?? '',
            'short_description' => $content['meta_description'] ?? '',
            'ai_answer_block' => $content['ai_answer_block'] ?? '',
            'best_for' => $use['best_for'] ?? [],
            'not_for' => $use['not_for'] ?? [],
            'faq_data' => $row['faqs'] ?? [],
            'alt_text' => $content['alt_text'] ?? '',
        ]);

        $svc = new ChannelDeployService();
        $method = new ReflectionMethod($svc, 'buildDeployPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($svc, $sku);

        $required = [
            'sku_id', 'shopify_product_id', 'title', 'meta_title', 'meta_description',
            'answer_block', 'best_for', 'not_for', 'faq', 'json_ld', 'alt_text',
        ];
        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $payload, "missing payload key: {$key}");
        }
        $this->assertNotSame('', trim((string) $payload['title']));
        $this->assertNotSame('', trim((string) $payload['answer_block']));
        $this->assertIsArray($payload['faq']);
    }

    /** @test publish deploy posts to N8N shopify-deploy webhook with HMAC */
    public function test_post_with_hmac_hits_shopify_deploy_webhook(): void
    {
        putenv('N8N_BASE_URL=http://n8n-mock.test');
        putenv('N8N_WEBHOOK_SECRET=ci-test-secret');
        $_ENV['N8N_BASE_URL'] = 'http://n8n-mock.test';
        $_ENV['N8N_WEBHOOK_SECRET'] = 'ci-test-secret';

        Http::fake([
            'http://n8n-mock.test/webhook/shopify-deploy' => Http::response(
                ['status' => 'deployed', 'shopify_product_id' => 'gid://shopify/Product/1', 'deployed_at' => '2026-06-03T12:00:00Z'],
                200
            ),
        ]);

        $payload = json_decode(
            file_get_contents($this->repoRoot . '/tests/fixtures/n8n/hero_deploy_payload_sample.json'),
            true
        );
        $body = json_encode($payload);
        $expectedSig = hash_hmac('sha256', $body, 'ci-test-secret');

        $svc = new ChannelDeployService();
        $post = new ReflectionMethod($svc, 'postWithHmac');
        $post->setAccessible(true);
        $result = $post->invoke($svc, 'http://n8n-mock.test/webhook/shopify-deploy', $payload, 'ci-test-secret');

        $this->assertSame(200, $result['status_code'] ?? 0);
        $this->assertSame('deployed', $result['body']['status'] ?? null);

        Http::assertSent(function ($request) use ($expectedSig) {
            return $request->url() === 'http://n8n-mock.test/webhook/shopify-deploy'
                && $request->hasHeader('X-N8N-Signature', $expectedSig);
        });
    }

    /** @test N8N workflow webhook path matches PHP default */
    public function test_shopify_workflow_path_matches_php_default(): void
    {
        $wf = json_decode(
            file_get_contents($this->repoRoot . '/n8n/workflows/shopify_deploy.json'),
            true
        );
        $path = null;
        foreach ($wf['nodes'] ?? [] as $node) {
            if (($node['type'] ?? '') === 'n8n-nodes-base.webhook') {
                $path = $node['parameters']['path'] ?? null;
                break;
            }
        }
        $this->assertSame('shopify-deploy', $path);

        $php = file_get_contents($this->repoRoot . '/backend/php/src/Services/ChannelDeployService.php');
        $this->assertStringContainsString("env('N8N_SHOPIFY_WEBHOOK_PATH', 'shopify-deploy')", $php);
    }
}
