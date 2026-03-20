<?php

namespace App\Controllers;

use App\Models\AuditLog;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditLogController
{
    /** Entity types whose entity_id is a SKU id (resolve to sku_code/title). */
    private const SKU_ENTITY_TYPES = ['sku', 'sku_publish', 'sku_faq_response', 'gate_status'];

    public function index(Request $request)
    {
        if (!Schema::hasTable('audit_log')) {
            return ResponseFormatter::format([]);
        }

        $orderColumn = Schema::hasColumn('audit_log', 'timestamp') ? 'timestamp' : 'created_at';
        $query = AuditLog::orderByDesc($orderColumn);

        if ($request->has('entity_type')) {
            $query->where('entity_type', $request->query('entity_type'));
        }

        if ($request->has('entity_id')) {
            $query->where('entity_id', $request->query('entity_id'));
        }

        if ($request->filled('user')) {
            $uid = $request->query('user');
            $query->where(function ($q) use ($uid) {
                $q->where('user_id', $uid);
                if (Schema::hasColumn('audit_log', 'actor_id')) {
                    $q->orWhere('actor_id', $uid);
                }
            });
        }
        if ($request->filled('sku')) {
            $query->where('entity_id', $request->query('sku'));
        }
        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        $limit = min((int) $request->query('limit', 100), 500);
        $logs = $query->limit($limit)->get();

        $enriched = $this->enrichLogsWithLabels($logs);

        return ResponseFormatter::format($enriched);
    }

    /**
     * Return distinct users and SKUs that appear in the audit log (for filter dropdowns).
     */
    public function filters()
    {
        if (!Schema::hasTable('audit_log')) {
            return ResponseFormatter::format(['users' => [], 'skus' => []]);
        }

        $users = [];
        $userIds = DB::table('audit_log')->whereNotNull('user_id')->distinct()->pluck('user_id');
        if (Schema::hasColumn('audit_log', 'actor_id')) {
            $actorIds = DB::table('audit_log')->whereNotNull('actor_id')->distinct()->pluck('actor_id');
            $userIds = $userIds->merge($actorIds)->unique()->filter()->values();
        }
        if ($userIds->isNotEmpty() && Schema::hasTable('users')) {
            $userRows = DB::table('users')->whereIn('id', $userIds->toArray())->select('id', 'first_name', 'last_name', 'email')->get();
            $userMap = [];
            foreach ($userRows as $u) {
                $name = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
                $userMap[$u->id] = $name ?: ($u->email ?? $u->id);
            }
            foreach ($userIds as $id) {
                $users[] = ['id' => $id, 'label' => $userMap[$id] ?? $id];
            }
        } else {
            foreach ($userIds as $id) {
                $users[] = ['id' => $id, 'label' => $id];
            }
        }

        $entityIds = DB::table('audit_log')->whereIn('entity_type', self::SKU_ENTITY_TYPES)->distinct()->pluck('entity_id');
        $skus = [];
        if ($entityIds->isNotEmpty() && Schema::hasTable('skus')) {
            $skuRows = DB::table('skus')->whereIn('id', $entityIds->toArray())->select('id', 'sku_code', 'title')->get();
            $skuMap = [];
            foreach ($skuRows as $s) {
                $skuMap[$s->id] = $s->sku_code ?: ($s->title ?? $s->id);
            }
            foreach ($entityIds as $id) {
                $skus[] = ['id' => $id, 'label' => $skuMap[$id] ?? $id];
            }
        } else {
            foreach ($entityIds as $id) {
                $skus[] = ['id' => $id, 'label' => $id];
            }
        }

        return ResponseFormatter::format([
            'users' => array_values($users),
            'skus' => array_values($skus),
        ]);
    }

    /**
     * Add actor_label and entity_label to each log row for display in the viewer.
     */
    private function enrichLogsWithLabels($logs): array
    {
        if ($logs->isEmpty()) {
            return [];
        }

        $userIds = $logs->pluck('user_id')->merge($logs->pluck('actor_id'))->filter()->unique()->values()->toArray();
        $userMap = [];
        if (!empty($userIds) && Schema::hasTable('users')) {
            $userRows = DB::table('users')->whereIn('id', $userIds)->select('id', 'first_name', 'last_name', 'email')->get();
            foreach ($userRows as $u) {
                $name = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
                $userMap[$u->id] = $name ?: ($u->email ?? $u->id);
            }
        }

        $entityIds = $logs->filter(fn ($r) => in_array($r->entity_type ?? '', self::SKU_ENTITY_TYPES, true))->pluck('entity_id')->unique()->filter()->values()->toArray();
        $entityMap = [];
        if (!empty($entityIds) && Schema::hasTable('skus')) {
            $skuRows = DB::table('skus')->whereIn('id', $entityIds)->select('id', 'sku_code', 'title')->get();
            foreach ($skuRows as $s) {
                $entityMap[$s->id] = $s->sku_code ?: ($s->title ?? $s->id);
            }
        }

        $result = [];
        foreach ($logs as $row) {
            $arr = $row->toArray();
            $actorId = $arr['actor_id'] ?? $arr['user_id'] ?? null;
            $arr['actor_label'] = $actorId ? ($userMap[$actorId] ?? $actorId) : null;
            $eid = $arr['entity_id'] ?? null;
            $arr['entity_label'] = ($eid && isset($entityMap[$eid])) ? $entityMap[$eid] : $eid;
            $result[] = $arr;
        }

        return $result;
    }
}
