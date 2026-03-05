<?php
use App\Controllers\AuthController;
use App\Controllers\SkuController;
use App\Controllers\ValidationController;
use App\Controllers\TierController;
use App\Controllers\ClusterController;
use App\Controllers\AuditController;
use App\Controllers\BriefController;
use App\Controllers\IntentsController;
use App\Controllers\DashboardController;
use App\Controllers\SemrushImportController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

// Unified API v1 — all spec-compliant routes live under /api/v1
Route::prefix('v1')->middleware('auth')->group(function () {
    // Auth — open login endpoint (no auth middleware)
    Route::post('/auth/login', [AuthController::class, 'login'])->withoutMiddleware('auth');

    // SKU endpoints — singular /sku/{sku_id}
    Route::get('/sku/{sku_id}', [SkuController::class, 'show']);
    Route::post('/sku/{sku_id}/validate', [ValidationController::class, 'validate'])->middleware('rbac:CONTENT_EDITOR,PRODUCT_SPECIALIST,CONTENT_LEAD,SEO_GOVERNOR,ADMIN');
    Route::put('/sku/{sku_id}/content', [SkuController::class, 'updateContent'])->middleware('rbac:CONTENT_EDITOR,PRODUCT_SPECIALIST,CHANNEL_MANAGER,SEO_GOVERNOR,CONTENT_LEAD,PORTFOLIO_HOLDER,ADMIN');
    Route::post('/sku/{sku_id}/publish', [SkuController::class, 'publish']);
    Route::get('/sku/{sku_id}/readiness', [SkuController::class, 'readiness']);

    // Queue + dashboard
    Route::get('/queue/today', [SkuController::class, 'queueToday']);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/audit-results/weekly-scores', [DashboardController::class, 'weeklyScores']);

    // Taxonomy & clusters
    Route::get('/clusters', [ClusterController::class, 'index']);
    Route::get('/taxonomy/intents', [IntentsController::class, 'index']);

    // Audit (category-level)
    Route::post('/audit/run', [AuditController::class, 'runByCategory'])->middleware('rbac:AI_OPS,ADMIN');
    Route::get('/audit/results/{category}', [AuditController::class, 'resultsByCategory']);

    // Briefs
    Route::post('/brief/generate', [BriefController::class, 'generate']);

    // ERP sync
    Route::post('/erp/sync', [TierController::class, 'erpSync'])->middleware('rbac:FINANCE,ADMIN');

    // Semrush CSV Import (permitted addition)
    Route::post('/admin/semrush-import', [SemrushImportController::class, 'import'])->middleware('rbac:ADMIN');

    // SOURCE: CIE_v232_UI_Restructure_Instructions.docx §2.1; CIE_v232_Developer_Amendment_Pack_v2.docx §4.2; openapi.yaml /sku/{sku_id}/suggestions/{suggestion_id}/status
    // Pass-through only: proxy dismiss/seen status to Python Engine endpoint without adding new controller logic.
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
