<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lists vector_retry_queue rows (status=queued) for ops when dashboard shows embedding degraded.
 */
class CieVectorRetryListCommand extends Command
{
    protected $signature = 'cie:vector-retry-list {--all : Include non-queued rows (last 50)}';

    protected $description = 'List vector_retry_queue pending items (sku_id, next_retry_at, errors)';

    public function handle(): int
    {
        if (! Schema::hasTable('vector_retry_queue')) {
            $this->error('Table vector_retry_queue does not exist.');

            return 1;
        }

        $q = DB::table('vector_retry_queue')->orderByDesc('created_at');
        if (! $this->option('all')) {
            $q->where('status', 'queued');
        }
        $rows = $q->limit(50)->get();

        if ($rows->isEmpty()) {
            $this->info('No rows found.');

            return 0;
        }

        $headers = ['id', 'sku_id', 'cluster_id', 'status', 'retry_count', 'next_retry_at', 'created_at', 'error_message'];
        $table = [];
        foreach ($rows as $r) {
            $table[] = [
                (string) ($r->id ?? ''),
                (string) ($r->sku_id ?? ''),
                (string) ($r->cluster_id ?? ''),
                (string) ($r->status ?? ''),
                (string) ($r->retry_count ?? '0'),
                $r->next_retry_at ? (string) $r->next_retry_at : '',
                $r->created_at ? (string) $r->created_at : '',
                $r->error_message ? substr((string) $r->error_message, 0, 80) : '',
            ];
        }
        $this->table($headers, $table);

        return 0;
    }
}
