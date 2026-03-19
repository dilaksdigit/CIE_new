<?php
use App\Controllers\AuthController;
use App\Controllers\SkuController;
use App\Controllers\ValidationController;
use App\Controllers\TierController;
use App\Controllers\ClusterController;
use App\Controllers\AuditController;
use App\Controllers\AuditLogController;
use App\Controllers\BriefController;
use App\Controllers\IntentsController;
use App\Controllers\DashboardController;
use App\Controllers\SemrushImportController;
use App\Controllers\AdminBusinessRulesController;
use App\Controllers\ConfigController;
use App\Controllers\BaselineController;
use App\Controllers\ShopifyProductPullController;
use App\Controllers\TierChangeController;
use App\Controllers\FAQController;
use App\Controllers\GscController;
use App\Controllers\BulkOpsController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

// Semrush import — spec path POST /api/admin/semrush-import (no /v1/); CLAUDE.md §3 Rule R1
Route::post('admin/semrush-import', [SemrushImportController::class, 'import'])->middleware(['auth', 'rbac:ADMIN']);

// ERP sync — spec alias at POST /api/admin/erp-sync (openapi.yaml ERP Integration tag)
Route::post('admin/erp-sync', [TierController::class, 'erpSync'])->middleware(['auth', 'rbac:ADMIN']);

// Unified API v1 — all spec-compliant routes live under /api/v1
// SOURCE: CLAUDE.md Section 3 R1; cie_v231_openapi.yaml (locked contract)
Route::prefix('v1')->middleware('auth')->group(function () {
    // Auth — open endpoints (no auth middleware)
    Route::post('/auth/login', [AuthController::class, 'login'])->withoutMiddleware('auth');
    Route::post('/auth/register', [AuthController::class, 'register'])->withoutMiddleware('auth');

    // SKU endpoints — SOURCE: openapi.yaml paths
    Route::get('/sku', [SkuController::class, 'index']);
    Route::post('/sku', [SkuController::class, 'store']);
    Route::get('/sku/stats', [SkuController::class, 'stats']);
    Route::get('/sku/{sku_id}', [SkuController::class, 'show']);
    // SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 1.1
    Route::post('/sku/validate', [ValidationController::class, 'validateByPayload'])->middleware('rbac:CONTENT_EDITOR,PRODUCT_SPECIALIST,CONTENT_LEAD,SEO_GOVERNOR,ADMIN');
    Route::post('/sku/{sku_id}/validate', [ValidationController::class, 'validate'])->middleware('rbac:CONTENT_EDITOR,PRODUCT_SPECIALIST,CONTENT_LEAD,SEO_GOVERNOR,ADMIN');
    Route::put('/sku/{sku_id}/content', [SkuController::class, 'updateContent'])->middleware('rbac:CONTENT_EDITOR,PRODUCT_SPECIALIST,CHANNEL_MANAGER,SEO_GOVERNOR,CONTENT_LEAD,ADMIN');
    Route::post('/sku/{sku_id}/publish', [SkuController::class, 'publish']);
    Route::get('/sku/{sku_id}/readiness', [SkuController::class, 'readiness']);
    // Tier change requests — RBAC-05 (CLAUDE.md Section 7 + Hardening Addendum); SOURCE: CIE_v232_Developer_Amendment_Pack_v2.docx
    Route::post('/sku/{sku_id}/tier-change-request', [TierChangeController::class, 'createRequest'])->middleware('rbac:CONTENT_EDITOR,PRODUCT_SPECIALIST,ADMIN');
    Route::post('/sku/{sku_id}/tier-change-approve', [TierChangeController::class, 'approveForSku'])->middleware('rbac:FINANCE,ADMIN');
    Route::get('/sku/{sku_id}/tier-change-status', [TierChangeController::class, 'getStatus'])->middleware('rbac:CONTENT_LEAD,FINANCE,ADMIN');
    Route::post('/tier-change-requests/{id}/approve-portfolio', [TierChangeController::class, 'approvePortfolio'])->middleware('rbac:CONTENT_LEAD,ADMIN');
    Route::get('/sku/{sku_id}/faq-suggestions', [SkuController::class, 'faqSuggestions']);
    Route::get('/faq/templates', [FAQController::class, 'getTemplates']);
    Route::post('/sku/{id}/faq', [FAQController::class, 'saveResponses']);
    Route::get('/sku/{sku_id}/audit-results', [SkuController::class, 'auditResults']);
    Route::get('/sku/{sku_id}/rollback-content', [SkuController::class, 'rollbackContent']);

    // Baseline capture — SOURCE: openapi.yaml
    // SOURCE: CIE_Master_Developer_Build_Spec.docx §17 Phase 2.1 + route table §2029
    Route::get('/gsc/status', [GscController::class, 'status'])->middleware('rbac:ADMIN');
    Route::post('/gsc/baseline/{sku_id}', [BaselineController::class, 'captureGsc']);
    Route::post('/ga4/baseline/{sku_id}', [BaselineController::class, 'captureGa4']);

    // Queue + dashboard — SOURCE: openapi.yaml
    Route::get('/queue/today', [SkuController::class, 'queueToday']);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/decay-alerts', [DashboardController::class, 'decayAlerts']);
    Route::get('/dashboard/channel-stats', [DashboardController::class, 'channelStats']);
    Route::get('/audit-results/weekly-scores', [DashboardController::class, 'weeklyScores']);
    Route::post('/audit-results/weekly-scores', [DashboardController::class, 'storeWeeklyScore']);

    // Taxonomy & clusters
    Route::get('/clusters', [ClusterController::class, 'index']);
    Route::post('/clusters', [ClusterController::class, 'store']);
    Route::put('/clusters/{id}', [ClusterController::class, 'update']);
    Route::get('/taxonomy/intents', [IntentsController::class, 'index']);

    // Audit (category-level)
    Route::post('/audit/run', [AuditController::class, 'runByCategory'])->middleware('rbac:AI_OPS,ADMIN');
    Route::get('/audit/results/{category}', [AuditController::class, 'resultsByCategory']);

    // Briefs — SOURCE: openapi.yaml
    Route::get('/briefs', [BriefController::class, 'index']);
    Route::post('/brief/generate', [BriefController::class, 'generate']);

    Route::post('/tiers/recalculate', [TierController::class, 'recalculate'])->middleware('rbac:FINANCE,ADMIN');

    // ERP sync — admin only (spec: POST /api/v1/erp/sync)
    Route::post('/erp/sync', [TierController::class, 'erpSync'])->middleware('rbac:ADMIN');

    Route::get('/config', [ConfigController::class, 'index']);
    Route::put('/config', [ConfigController::class, 'update'])->middleware('rbac:ADMIN');

    Route::get('/admin/business-rules', [AdminBusinessRulesController::class, 'index'])->middleware('rbac:ADMIN');
    Route::get('/admin/business-rules/audit', [AdminBusinessRulesController::class, 'audit'])->middleware('rbac:ADMIN');
    Route::put('/admin/business-rules/{key}', [AdminBusinessRulesController::class, 'update'])->middleware('rbac:ADMIN');
    Route::post('/admin/business-rules/{key}/approve', [AdminBusinessRulesController::class, 'approve'])->middleware('rbac:ADMIN');

    // Semrush CSV Import — GET/DELETE remain under v1; POST import is at /api/admin/semrush-import (see above)
    Route::get('/admin/semrush-import/latest', [SemrushImportController::class, 'latest'])->middleware('rbac:ADMIN,CONTENT_LEAD,SEO_GOVERNOR');
    Route::delete('/admin/semrush-import/{batch_date}', [SemrushImportController::class, 'delete'])->middleware('rbac:ADMIN');

    // Shopify product pull — admin only; does not affect deploy/publish
    Route::get('/shopify/status', [ShopifyProductPullController::class, 'status']);
    Route::get('/shopify/products', [ShopifyProductPullController::class, 'index'])->middleware('rbac:ADMIN');
    Route::post('/shopify/sync', [ShopifyProductPullController::class, 'sync'])->middleware('rbac:ADMIN');

    // Bulk Ops (Admin) — summary, counts, and execution; zero hardcode in frontend
    Route::get('/admin/bulk-ops/summary', [BulkOpsController::class, 'summary'])->middleware('rbac:ADMIN');
    Route::get('/admin/bulk-ops/tier-change-requests', [BulkOpsController::class, 'listTierChangeRequests'])->middleware('rbac:ADMIN');
    Route::post('/admin/bulk-ops/cluster-assignment', [BulkOpsController::class, 'clusterAssignment'])->middleware('rbac:ADMIN');
    Route::post('/admin/bulk-ops/status-change', [BulkOpsController::class, 'statusChange'])->middleware('rbac:ADMIN');
    Route::post('/admin/bulk-ops/faq-apply', [BulkOpsController::class, 'faqApply'])->middleware('rbac:ADMIN');
    Route::get('/admin/bulk-ops/export', [BulkOpsController::class, 'export'])->middleware('rbac:ADMIN');

    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    // Suggestion status proxy to Python Engine — SOURCE: openapi.yaml
    Route::post('/sku/{sku_id}/suggestions/{suggestion_id}/status', function (\Illuminate\Http\Request $request, string $sku_id, string $suggestion_id) {
        $engineBase = rtrim(env('CIE_ENGINE_BASE_URL', 'http://localhost:8000/api/v1'), '/');
        $url = $engineBase . '/sku/' . urlencode($sku_id) . '/suggestions/' . urlencode($suggestion_id) . '/status';
        $client = Http::acceptJson();
        $token = env('CIE_ENGINE_TOKEN');
        if (!empty($token)) { $client = $client->withToken($token); }
        $response = $client->post($url, $request->all());
        return response()->json($response->json(), $response->status());
    })->name('sku.suggestions.status');
});
