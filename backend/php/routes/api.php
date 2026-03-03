<?php
use App\Controllers\AuthController;
use App\Controllers\SkuController;
use App\Controllers\ValidationController;
use App\Controllers\TierController;
use App\Controllers\ClusterController;
use App\Controllers\ClusterChangeRequestController;
use App\Controllers\AuditController;
use App\Controllers\AuditLogController;
use App\Controllers\BriefController;
use App\Controllers\IntentsController;
use App\Controllers\DashboardController;
use App\Controllers\ConfigController;
use App\Controllers\AdminBusinessRulesController;
use App\Controllers\SemrushImportController;
use App\Controllers\BaselineController;
use Illuminate\Support\Facades\Route;

// GET /api — base URL (avoids 404 when visiting http://localhost:8080/api)
Route::get('/', function () {
    return response()->json([
        'name'    => 'CIE API',
        'version' => '2.3.2',
        'status'  => 'running',
        'docs'    => 'Use /api/auth/login, /api/skus, /api/clusters, etc.',
    ]);
});

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

Route::middleware('auth')->group(function () {
    // SKU Management
    Route::get('/skus', [SkuController::class, 'index']);
    Route::get('/skus/stats', [SkuController::class, 'stats']);
    Route::get('/skus/{id}', [SkuController::class, 'show']);
    Route::post('/skus', [SkuController::class, 'store'])->middleware('rbac:CONTENT_EDITOR,CHANNEL_MANAGER,ADMIN');
    Route::put('/skus/{id}', [SkuController::class, 'update'])->middleware('rbac:CONTENT_EDITOR,PRODUCT_SPECIALIST,CHANNEL_MANAGER,SEO_GOVERNOR,CONTENT_LEAD,PORTFOLIO_HOLDER,ADMIN');
    Route::post('/skus/{id}/validate', [ValidationController::class, 'validate'])->middleware('rbac:CONTENT_EDITOR,PRODUCT_SPECIALIST,CONTENT_LEAD,SEO_GOVERNOR,ADMIN');
    Route::post('/skus/{id}/intents', [SkuController::class, 'attachIntents'])->middleware('rbac:CONTENT_EDITOR,CHANNEL_MANAGER,SEO_GOVERNOR,ADMIN');
    Route::get('/skus/{id}/readiness', [SkuController::class, 'readiness']);
    Route::get('/skus/{id}/faq-suggestions', [SkuController::class, 'faqSuggestions']);
    Route::get('/queue/today', [SkuController::class, 'queueToday']);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/decay-alerts', [DashboardController::class, 'decayAlerts']);

    // Config (Admin-only for update)
    Route::get('/config', [ConfigController::class, 'index']);
    Route::put('/config', [ConfigController::class, 'update'])->middleware('rbac:ADMIN');

    // Admin Business Rules (Phase 0 Check 0.1) — read/write business_rules table
    Route::get('/admin/business-rules/audit', [AdminBusinessRulesController::class, 'audit'])->middleware('rbac:ADMIN');
    Route::get('/admin/business-rules', [AdminBusinessRulesController::class, 'index'])->middleware('rbac:ADMIN');
    Route::put('/admin/business-rules/{key}', [AdminBusinessRulesController::class, 'update'])->middleware('rbac:ADMIN');
    Route::post('/admin/business-rules/{key}/approve', [AdminBusinessRulesController::class, 'approve'])->middleware('rbac:ADMIN');

    // Semrush CSV Import (Admin-only)
    Route::post('/admin/semrush-import', [SemrushImportController::class, 'import'])->middleware('rbac:ADMIN');
    Route::get('/admin/semrush-import/latest', [SemrushImportController::class, 'latest'])->middleware('rbac:ADMIN');
    Route::delete('/admin/semrush-import/{batch_date}', [SemrushImportController::class, 'delete'])->middleware('rbac:ADMIN');

    // Tier Management
    Route::post('/tiers/recalculate', [TierController::class, 'recalculate'])->middleware('rbac:FINANCE,ADMIN');

    // Cluster Management
    Route::get('/clusters', [ClusterController::class, 'index']);
    Route::get('/clusters/{id}', [ClusterController::class, 'show']);
    Route::post('/clusters', [ClusterController::class, 'store'])->middleware('rbac:SEO_GOVERNOR,ADMIN');
    Route::put('/clusters/{id}', [ClusterController::class, 'update'])->middleware('rbac:SEO_GOVERNOR,ADMIN');
    // v2.3.2 Patch 5: Cluster governance (propose → review → approve)
    Route::get('/cluster-change-requests', [ClusterChangeRequestController::class, 'index']);
    Route::post('/cluster-change-requests', [ClusterChangeRequestController::class, 'store']);
    Route::get('/cluster-change-requests/{id}', [ClusterChangeRequestController::class, 'show']);
    Route::post('/cluster-change-requests/{id}/review', [ClusterChangeRequestController::class, 'review'])->middleware('rbac:SEO_GOVERNOR,ADMIN');
    Route::post('/cluster-change-requests/{id}/approve', [ClusterChangeRequestController::class, 'approve'])->middleware('rbac:SEO_GOVERNOR,ADMIN');
    Route::post('/cluster-change-requests/{id}/reject', [ClusterChangeRequestController::class, 'reject'])->middleware('rbac:SEO_GOVERNOR,ADMIN');

    // Taxonomy (Unified API 7.1). 3.2: Only ADMIN can modify 9-intent taxonomy.
    Route::get('/taxonomy/intents', [IntentsController::class, 'index']);
    Route::put('/taxonomy/intents/{id}', [IntentsController::class, 'update'])->middleware('rbac:ADMIN');

    // Audit Management
    Route::post('/audit/{sku_id}', [AuditController::class, 'runAudit'])->middleware('rbac:AI_OPS,ADMIN');
    Route::post('/audit/run', [AuditController::class, 'runByCategory'])->middleware('rbac:AI_OPS,ADMIN');
    Route::get('/audit/{sku_id}/history', [AuditController::class, 'history']);
    Route::get('/audit-result/{auditId}', [AuditController::class, 'getResult']);
    Route::get('/audit/results/{category}', [AuditController::class, 'resultsByCategory']);

    // Audit Log Trail
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('rbac:CONTENT_LEAD,SEO_GOVERNOR,ADMIN');

    // Brief Management
    Route::get('/briefs', [BriefController::class, 'index']);
    Route::post('/briefs', [BriefController::class, 'store'])->middleware('rbac:CONTENT_EDITOR,CONTENT_LEAD,PORTFOLIO_HOLDER,ADMIN');
    Route::put('/briefs/{id}', [BriefController::class, 'update'])->middleware('rbac:CONTENT_EDITOR,CONTENT_LEAD,PORTFOLIO_HOLDER,ADMIN');
    Route::post('/brief/generate', [BriefController::class, 'generate']);
    Route::get('/briefs/{id}', [BriefController::class, 'show']);

    // ERP Sync (Unified API 7.1)
    Route::post('/erp/sync', [TierController::class, 'erpSync'])->middleware('rbac:FINANCE,ADMIN');

    // GSC + GA4 baselines (fail-soft, never block publish)
    Route::post('/gsc/baseline/{sku_id}', [BaselineController::class, 'captureGscBaseline']);
    Route::post('/ga4/baseline/{sku_id}', [BaselineController::class, 'captureGa4Baseline']);
});

// Unified API v1 — same routes under /api/v1 for spec compliance
Route::prefix('v1')->middleware('auth')->group(function () {
    Route::post('/sku/validate', [ValidationController::class, 'validateByPayload'])->middleware('rbac:CONTENT_EDITOR,PRODUCT_SPECIALIST,CONTENT_LEAD,SEO_GOVERNOR,ADMIN');
    Route::get('/queue/today', [SkuController::class, 'queueToday']);
    Route::get('/skus', [SkuController::class, 'index']);
    Route::get('/skus/stats', [SkuController::class, 'stats']);
    Route::get('/skus/{id}', [SkuController::class, 'show']);
    Route::get('/skus/{id}/readiness', [SkuController::class, 'readiness']);
    Route::get('/skus/{id}/faq-suggestions', [SkuController::class, 'faqSuggestions']);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/decay-alerts', [DashboardController::class, 'decayAlerts']);
    // SOURCE: openapi.yaml /audit-results/weekly-scores GET
    Route::get('/audit-results/weekly-scores', [DashboardController::class, 'weeklyScores']);
    // SOURCE: openapi.yaml /audit-results/weekly-scores POST
    // SOURCE: CIE_v232_Developer_Amendment_Pack_v2.docx §4.1
    Route::post('/audit-results/weekly-scores', [DashboardController::class, 'storeWeeklyScore'])->middleware('rbac:CONTENT_LEAD,SEO_GOVERNOR,ADMIN');
    Route::post('/skus', [SkuController::class, 'store'])->middleware('rbac:CONTENT_EDITOR,CHANNEL_MANAGER,ADMIN');
    Route::put('/skus/{id}', [SkuController::class, 'update'])->middleware('rbac:CONTENT_EDITOR,PRODUCT_SPECIALIST,CHANNEL_MANAGER,SEO_GOVERNOR,CONTENT_LEAD,PORTFOLIO_HOLDER,ADMIN');
    Route::post('/skus/{id}/validate', [ValidationController::class, 'validate'])->middleware('rbac:CONTENT_EDITOR,PRODUCT_SPECIALIST,CONTENT_LEAD,SEO_GOVERNOR,ADMIN');
    Route::post('/tiers/recalculate', [TierController::class, 'recalculate'])->middleware('rbac:FINANCE,ADMIN');
    Route::get('/clusters', [ClusterController::class, 'index']);
    Route::get('/clusters/{id}', [ClusterController::class, 'show']);
    Route::post('/clusters', [ClusterController::class, 'store'])->middleware('rbac:SEO_GOVERNOR,ADMIN');
    Route::put('/clusters/{id}', [ClusterController::class, 'update'])->middleware('rbac:SEO_GOVERNOR,ADMIN');
    Route::get('/cluster-change-requests', [ClusterChangeRequestController::class, 'index']);
    Route::post('/cluster-change-requests', [ClusterChangeRequestController::class, 'store']);
    Route::get('/cluster-change-requests/{id}', [ClusterChangeRequestController::class, 'show']);
    Route::post('/cluster-change-requests/{id}/review', [ClusterChangeRequestController::class, 'review'])->middleware('rbac:SEO_GOVERNOR,ADMIN');
    Route::post('/cluster-change-requests/{id}/approve', [ClusterChangeRequestController::class, 'approve'])->middleware('rbac:SEO_GOVERNOR,ADMIN');
    Route::post('/cluster-change-requests/{id}/reject', [ClusterChangeRequestController::class, 'reject'])->middleware('rbac:SEO_GOVERNOR,ADMIN');
    Route::get('/taxonomy/intents', [IntentsController::class, 'index']);
    Route::put('/taxonomy/intents/{id}', [IntentsController::class, 'update'])->middleware('rbac:ADMIN');
    Route::post('/audit/run', [AuditController::class, 'runByCategory'])->middleware('rbac:AI_OPS,ADMIN');
    Route::get('/audit/results/{category}', [AuditController::class, 'resultsByCategory']);
    Route::post('/brief/generate', [BriefController::class, 'generate']);
    Route::post('/erp/sync', [TierController::class, 'erpSync'])->middleware('rbac:FINANCE,ADMIN');
});
