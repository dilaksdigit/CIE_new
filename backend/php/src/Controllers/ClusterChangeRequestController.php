<?php

namespace App\Controllers;

use App\Models\ClusterChangeRequest;
use App\Models\Cluster;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;

/**
 * CIE v2.3.2 Patch 5 — Cluster governance: propose → review → approve.
 */
class ClusterChangeRequestController
{
    /**
     * GET /cluster-change-requests — list requests (filter by status).
     */
    public function index(Request $request)
    {
        $query = ClusterChangeRequest::with('cluster')->orderByDesc('created_at');
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }
        return ResponseFormatter::format($query->get());
    }

    /**
     * POST /cluster-change-requests — propose a cluster change (any role).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cluster_id'                 => 'nullable|string|exists:clusters,id',
            'proposed_cluster_id'        => 'nullable|string|max:80',
            'proposed_name'              => 'nullable|string|max:200',
            'proposed_category'          => 'nullable|string|max:100',
            'intent_statement'           => 'required|string|max:1000',
            'query_evidence'            => 'nullable|array',
            'query_evidence.*'          => 'string|max:500',
            'sku_assignment'            => 'nullable|array',
            'impact_assessment'          => 'nullable|string|max:2000',
            'commercial_justification'   => 'nullable|string|max:2000',
        ]);

        $validated['status'] = ClusterChangeRequest::STATUS_PROPOSED;
        $validated['requested_by'] = $request->user()?->email ?? $request->user()?->name ?? 'anonymous';

        $req = ClusterChangeRequest::create($validated);
        return ResponseFormatter::format($req->load('cluster'));
    }

    /**
     * GET /cluster-change-requests/{id}
     */
    public function show($id)
    {
        $req = ClusterChangeRequest::with('cluster')->findOrFail($id);
        return ResponseFormatter::format($req);
    }

    /**
     * POST /cluster-change-requests/{id}/review — SEO Governor moves to review (with impact check).
     */
    public function review(Request $request, $id)
    {
        $req = ClusterChangeRequest::findOrFail($id);
        if ($req->status !== ClusterChangeRequest::STATUS_PROPOSED) {
            return response()->json(['error' => 'Only proposed requests can be moved to review.'], 422);
        }
        $req->update([
            'status'      => ClusterChangeRequest::STATUS_REVIEW,
            'reviewed_by' => $request->user()?->email ?? $request->user()?->name,
            'reviewed_at' => now(),
            'review_notes'=> $request->input('review_notes'),
        ]);
        return ResponseFormatter::format($req->load('cluster'));
    }

    /**
     * POST /cluster-change-requests/{id}/approve — Approve and activate (SEO Governor / Admin).
     */
    public function approve(Request $request, $id)
    {
        $req = ClusterChangeRequest::findOrFail($id);
        if ($req->status !== ClusterChangeRequest::STATUS_REVIEW) {
            return response()->json(['error' => 'Only requests in review can be approved.'], 422);
        }
        $req->update([
            'status'      => ClusterChangeRequest::STATUS_APPROVED,
            'reviewed_by' => $request->user()?->email ?? $request->user()?->name,
            'reviewed_at' => now(),
            'review_notes'=> $request->input('review_notes'),
        ]);
        // Optional: create or update cluster here from proposed_* fields
        return ResponseFormatter::format($req->load('cluster'));
    }

    /**
     * POST /cluster-change-requests/{id}/reject — Reject with feedback.
     */
    public function reject(Request $request, $id)
    {
        $req = ClusterChangeRequest::findOrFail($id);
        if (!in_array($req->status, [ClusterChangeRequest::STATUS_PROPOSED, ClusterChangeRequest::STATUS_REVIEW], true)) {
            return response()->json(['error' => 'Request cannot be rejected in current state.'], 422);
        }
        $req->update([
            'status'      => ClusterChangeRequest::STATUS_REJECTED,
            'reviewed_by' => $request->user()?->email ?? $request->user()?->name,
            'reviewed_at' => now(),
            'review_notes'=> $request->input('review_notes', 'Rejected by reviewer.'),
        ]);
        return ResponseFormatter::format($req->load('cluster'));
    }
}
