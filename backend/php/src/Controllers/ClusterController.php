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
}
