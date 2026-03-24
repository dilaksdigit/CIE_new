<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// SOURCE: CIE_Master_Developer_Build_Spec.docx §6.4 — url_performance
// Note: Spec names PK `perf_id` (UUID); implementation uses Laravel surrogate `id` (INT AUTO_INCREMENT).
// Functionally equivalent. GAP_LOG: accepted per AD-2 (surrogate PK convention).
class UrlPerformance extends Model
{
    protected $table = 'url_performance';

    public $timestamps = false;

    protected $guarded = [];
}
