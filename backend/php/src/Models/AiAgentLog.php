<?php
// SOURCE: CIE_Master_Developer_Build_Spec.docx §4.5
// FIX: AI-14 — AI Agent call logging

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAgentLog extends Model
{
    protected $table = 'ai_agent_logs';

    public $timestamps = false;

    protected $fillable = [
        'sku_id',
        'function_called',
        'prompt_hash',
        'response_received',
        'confidence_score',
        'status',
    ];

    protected $casts = [
        'response_received' => 'boolean',
        'confidence_score' => 'float',
    ];
}
