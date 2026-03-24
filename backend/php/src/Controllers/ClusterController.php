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

    public function store(Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
        ]);

        $cluster = Cluster::create($request->only(['name', 'category']));
        return ResponseFormatter::format($cluster, 'Created', 201);
    }

    public function update(Request $request, $id) {
        $cluster = Cluster::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|nullable|string|max:255',
            'intent_statement' => 'sometimes|string|max:2000',
            'is_locked' => 'sometimes|boolean',
            'requires_approval' => 'sometimes|boolean',
            'approval_status' => 'sometimes|string|in:DRAFT,PENDING,APPROVED,REJECTED',
        ]);

        $cluster->update($validated);
        return ResponseFormatter::format($cluster);
    }
}
