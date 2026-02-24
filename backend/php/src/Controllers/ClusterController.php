<?php
namespace App\Controllers;

use App\Models\Cluster;
use App\Services\PermissionService;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;

class ClusterController {
    public function __construct(private PermissionService $permissionService) {}

    public function index() {
        return ResponseFormatter::format(Cluster::withCount('skus')->get());
    }

    public function show($id) {
        $cluster = Cluster::with(['skus', 'primaryIntent'])->findOrFail($id);
        return ResponseFormatter::format($cluster);
    }

    /**
     * 3.2: Only SEO_GOVERNOR can modify cluster intent statements. ADMIN may update other cluster fields only.
     */
    public function update(Request $request, $id) {
        $cluster = Cluster::findOrFail($id);
        $data = $request->all();
        $intentKeys = ['primary_intent_id', 'intent_statement', 'intent_description', 'name'];
        $hasIntentChange = false;
        foreach ($intentKeys as $key) {
            if (array_key_exists($key, $data)) {
                $hasIntentChange = true;
                break;
            }
        }
        if ($hasIntentChange && !$this->permissionService->canModifyClusterIntent(auth()->user())) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Only SEO Governor can modify cluster intent statements.',
            ], 403);
        }
        $cluster->update($data);
        return ResponseFormatter::format($cluster);
    }
}
