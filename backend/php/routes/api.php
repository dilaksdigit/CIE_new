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
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

// Unified API v1 — all spec-compliant routes live under /api/v1
Route::prefix('v1')->middleware('auth')->group(function () {
    // Auth — open endpoints (no auth middleware)
    Route::post('/auth/login', [AuthController::class, 'login'])->withoutMiddleware('auth');
    Route::post('/auth/register', [AuthController::class, 'register'])->withoutMiddleware('auth');

    // SKU endpoints — singular /sku/{sku_id}
    Route::get('/sku', [SkuController::class, 'index']);
    Route::post('/sku', [SkuController::class, 'store']);
    Route::get('/sku/stats', [SkuController::class, 'stats']);
    Route::get('/sku/{sku_id}', [SkuController::class, 'show']);
    Route::post('/sku/{sku_id}/validate', [ValidationController::class, 'validate'])->middleware('rbac:CONTENT_EDITOR,PRODUCT_SPECIALIST,CONTENT_LEAD,SEO_GOVERNOR,ADMIN');
    Route::put('/sku/{sku_id}/content', [SkuController::class, 'updateContent'])->middleware('rbac:CONTENT_EDITOR,PRODUCT_SPECIALIST,CHANNEL_MANAGER,SEO_GOVERNOR,CONTENT_LEAD,PORTFOLIO_HOLDER,ADMIN');
    Route::post('/sku/{sku_id}/publish', [SkuController::class, 'publish']);
    Route::get('/sku/{sku_id}/readiness', [SkuController::class, 'readiness']);
    Route::get('/sku/{sku_id}/faq-suggestions', [SkuController::class, 'faqSuggestions']);
    Route::get('/sku/{sku_id}/audit-results', [SkuController::class, 'auditResults']);

    // Queue + dashboard
    Route::get('/queue/today', [SkuController::class, 'queueToday']);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/decay-alerts', [DashboardController::class, 'decayAlerts']);
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

    // Briefs
    Route::get('/briefs', [BriefController::class, 'index']);
    Route::post('/brief/generate', [BriefController::class, 'generate']);

    // Tiers
    Route::post('/tiers/recalculate', [TierController::class, 'recalculate'])->middleware('rbac:FINANCE,ADMIN');

    // ERP sync
    Route::post('/erp/sync', [TierController::class, 'erpSync'])->middleware('rbac:FINANCE,ADMIN');

    // Config
    Route::get('/config', [ConfigController::class, 'index']);
    Route::put('/config', [ConfigController::class, 'update'])->middleware('rbac:ADMIN');

    // Admin Business Rules
    Route::get('/admin/business-rules', [AdminBusinessRulesController::class, 'index'])->middleware('rbac:ADMIN');
    Route::get('/admin/business-rules/audit', [AdminBusinessRulesController::class, 'audit'])->middleware('rbac:ADMIN');
    Route::put('/admin/business-rules/{key}', [AdminBusinessRulesController::class, 'update'])->middleware('rbac:ADMIN');
    Route::post('/admin/business-rules/{key}/approve', [AdminBusinessRulesController::class, 'approve'])->middleware('rbac:ADMIN');

    // Semrush CSV Import
    Route::post('/admin/semrush-import', [SemrushImportController::class, 'import'])->middleware('rbac:ADMIN');
    Route::get('/admin/semrush-import/latest', [SemrushImportController::class, 'latest'])->middleware('rbac:ADMIN');
    Route::delete('/admin/semrush-import/{batch_date}', [SemrushImportController::class, 'delete'])->middleware('rbac:ADMIN');

    // Audit logs
    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    // Suggestion status proxy to Python Engine
    Route::post('/sku/{sku_id}/suggestions/{suggestion_id}/status', function (\Illuminate\Http\Request $request, string $sku_id, string $suggestion_id) {
        $engineBase = rtrim(env('CIE_ENGINE_BASE_URL', 'http://localhost:8000/api/v1'), '/');
        $url = $engineBase . '/sku/' . urlencode($sku_id) . '/suggestions/' . urlencode($suggestion_id) . '/status';

        $client = Http::acceptJson();
        $token = env('CIE_ENGINE_TOKEN');
        if (!empty($token)) {
            $client = $client->withToken($token);
        }

        $response = $client->post($url, $request->all());

        return response()->json($response->json(), $response->status());
    })->name('sku.suggestions.status');
});
