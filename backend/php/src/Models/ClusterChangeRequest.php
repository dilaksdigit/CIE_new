<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * CIE v2.3.2 Patch 5 — Cluster governance: propose → review → approve workflow.
 * Table: cluster_change_requests (create via migration or manual schema).
 */
class ClusterChangeRequest extends Model
{
    protected $table = 'cluster_change_requests';

    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_REVIEW = 'review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'cluster_id',
        'proposed_cluster_id',
        'proposed_name',
        'proposed_category',
        'intent_statement',
        'query_evidence',
        'sku_assignment',
        'impact_assessment',
        'commercial_justification',
        'status',
        'requested_by',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
    ];

    protected $casts = [
        'query_evidence' => 'array',
        'sku_assignment' => 'array',
        'reviewed_at'    => 'datetime',
    ];

    public function cluster()
    {
        return $this->belongsTo(Cluster::class, 'cluster_id');
    }
}
