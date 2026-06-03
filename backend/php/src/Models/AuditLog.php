<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    protected $table = 'audit_log';
    public $timestamps = false;
    protected $guarded = [];
    protected $keyType = 'string';
    public $incrementing = false;

    /** Remove canonical columns if table does not have them yet (pre-026/035). */
    protected static function booted(): void
    {
        static::creating(function (AuditLog $log) {
            if (empty($log->getAttribute($log->getKeyName()))) {
                $log->setAttribute($log->getKeyName(), (string) Str::uuid());
            }
        });

        static::saving(function (AuditLog $log) {
            if (!Schema::hasTable($log->getTable())) {
                return;
            }
            $cols = Schema::getColumnListing($log->getTable());
            foreach (['actor_id', 'actor_role', 'timestamp'] as $key) {
                if (!in_array($key, $cols, true) && array_key_exists($key, $log->getAttributes())) {
                    $log->offsetUnset($key);
                }
            }
        });
    }
}

