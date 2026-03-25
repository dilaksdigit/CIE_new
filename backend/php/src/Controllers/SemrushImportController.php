<?php
namespace App\Controllers;

use App\Models\AuditLog;
use App\Services\SemrushParserService;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx Sections 3–5

class SemrushImportController
{
    /** @var int SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx §4.2 — max upload 10MB */
    private const MAX_IMPORT_BYTES = 10485760;

    /**
     * POST /api/admin/semrush-import
     *
     * SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx §4.2 — whole-file validation; transactional insert (FIX SEM-03, SEM-04)
     */
    public function import(Request $request)
    {
        // SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx §4.2 + §4.1 — multipart + 10MB; parsing delegated to SemrushParserService
        $user = auth()->user();
        if (!$user || !optional($user->role)->name || strtoupper($user->role->name) !== 'ADMIN') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $request->validate([
            'file' => 'required|file|max:10240|mimes:csv,txt',
        ]);

        $file = $request->file('file');
        if (!$file->isValid()) {
            // SOURCE: CIE_v232_FINAL_Developer_Instruction.docx §7.2 API-15
            return ResponseFormatter::semrushError(
                422,
                'Validation failed',
                'File could not be read as CSV. Check the file is a standard Semrush export.'
            );
        }

        if ($file->getSize() > self::MAX_IMPORT_BYTES) {
            // SOURCE: CIE_v232_FINAL_Developer_Instruction.docx §7.2 API-15
            return ResponseFormatter::semrushError(422, 'Validation failed', 'File exceeds 10MB limit.', [
                'errors' => ['File exceeds 10MB limit.'],
                'rows_imported' => 0,
            ]);
        }

        $username = (string) ($user->name ?? $user->email ?? 'system');
        $parser = new SemrushParserService();
        $result = $parser->parseAndValidate($file, $username);

        if ($result->hasErrors()) {
            $payload = [
                'error' => 'Validation failed',
                'detail' => $result->getFirstError(),
            ];
            if ($result->rowErrors !== []) {
                $payload['errors'] = $result->rowErrors;
                $payload['rows_imported'] = 0;
            }
            // SOURCE: CIE_v232_FINAL_Developer_Instruction.docx §7.2 API-15
            return response()->json($payload, 422);
        }

        $insertData = $result->getRows();
        $importBatch = $result->getBatch();
        $importBatchId = $result->importBatchId;

        try {
            DB::transaction(function () use ($insertData, $importBatch, $user) {
                if ($insertData !== []) {
                    DB::table('semrush_imports')->insert($insertData);
                }
                AuditLog::create([
                    'entity_type' => 'semrush_import',
                    'entity_id'   => $importBatch,
                    'action'      => 'import',
                    'field_name'  => 'import_batch',
                    'old_value'   => null,
                    'new_value'   => $importBatch,
                    'actor_id'    => (string) (auth()->id() ?? ''),
                    'actor_role'  => optional(optional($user)->role)->name ?? '',
                    'timestamp'   => now(),
                ]);
            });
        } catch (\Throwable $e) {
            // SOURCE: CIE_v232_FINAL_Developer_Instruction.docx §7.2 API-15
            return ResponseFormatter::semrushError(500, 'Validation failed', 'Import failed. No rows were saved.', [
                'errors' => [$e->getMessage()],
                'rows_imported' => 0,
            ]);
        }

        $keywordCount = count(array_unique(array_map(static function ($row) {
            return (string) ($row['keyword'] ?? '');
        }, $result->parsedRows)));

        return response()->json([
            'status' => 'imported',
            'import_batch'    => $importBatch,
            'import_batch_id' => $importBatchId,
            'rows_imported'   => $result->getRowCount(),
            'keyword_count'   => $keywordCount,
            'errors'          => [],
        ], 200);
    }

    /**
     * GET /api/v1/admin/semrush-import/latest
     * Allowed for ADMIN (full) and CONTENT_LEAD/SEO_GOVERNOR (read-only for Leadership review screen).
     * Query param: filter=quick_wins|rank_movement|competitor_gaps (optional).
     * - quick_wins: position 11–30, keyword_difficulty < 40, search_volume > 500, tier hero/support (CLAUDE.md §13).
     * - rank_movement: latest batch rows with position and prev_position (position changes).
     * - competitor_gaps: gap keywords (position > 10 or null) grouped by sku_code.
     */
    public function latest(Request $request)
    {
        $user = auth()->user();
        if (!$user || !optional($user->role)->name) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $role = strtoupper((string) $user->role->name);
        $allowedRoles = ['ADMIN', 'CONTENT_LEAD', 'SEO_GOVERNOR'];
        if (!in_array($role, $allowedRoles, true)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $filter = $request->query('filter');
        $maxBatch = DB::table('semrush_imports')->selectRaw('MAX(import_batch) as mb')->value('mb');
        if ($maxBatch === null) {
            if ($filter === 'quick_wins') {
                return response()->json(['filter' => 'quick_wins', 'rows' => []], 200);
            }
            if ($filter === 'rank_movement') {
                return response()->json(['filter' => 'rank_movement', 'rows' => []], 200);
            }
            if ($filter === 'competitor_gaps') {
                return response()->json(['filter' => 'competitor_gaps', 'by_sku' => []], 200);
            }
            return response()->json(['history' => []], 200);
        }

        if ($filter === 'quick_wins') {
            // SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx §3.1 — prefer keyword_diff column when present
            $diffCol = null;
            if (Schema::hasColumn('semrush_imports', 'keyword_diff')) {
                $diffCol = 'semrush_imports.keyword_diff';
            } elseif (Schema::hasColumn('semrush_imports', 'keyword_difficulty')) {
                $diffCol = 'semrush_imports.keyword_difficulty';
            }
            $quickWins = DB::table('semrush_imports')
                ->join('skus', 'skus.sku_code', '=', 'semrush_imports.sku_code')
                ->where('semrush_imports.import_batch', $maxBatch)
                ->whereBetween('semrush_imports.position', [11, 30])
                ->whereRaw('(semrush_imports.search_volume IS NULL OR semrush_imports.search_volume > 500)')
                ->whereIn(DB::raw('LOWER(TRIM(skus.tier))'), ['hero', 'support']);
            if ($diffCol !== null) {
                $quickWins->whereRaw("({$diffCol} IS NULL OR {$diffCol} < 40)");
            }
            $quickWins = $quickWins
                ->select(
                    'semrush_imports.keyword',
                    'semrush_imports.position',
                    'semrush_imports.prev_position',
                    'semrush_imports.search_volume',
                    'semrush_imports.sku_code',
                    'skus.tier as tier'
                )
                ->orderBy('semrush_imports.position')
                ->limit(500)
                ->get();
            return response()->json(['filter' => 'quick_wins', 'rows' => $quickWins], 200);
        }

        if ($filter === 'rank_movement') {
            // SOURCE: CIE_v232_FINAL_Developer_Instruction.docx §5.6 — filters work on /review/semrush
            // SOURCE: CLAUDE.md §13 — Semrush CSV can include sku_code; tier resolved via join to skus
            $movement = DB::table('semrush_imports')
                ->leftJoin('skus', 'skus.sku_code', '=', 'semrush_imports.sku_code')
                ->where('semrush_imports.import_batch', $maxBatch)
                ->whereNotNull('prev_position')
                ->select(
                    'semrush_imports.keyword',
                    'semrush_imports.position',
                    'semrush_imports.prev_position',
                    'semrush_imports.search_volume',
                    'semrush_imports.sku_code',
                    'skus.tier as tier'
                )
                ->orderByRaw('(semrush_imports.position - semrush_imports.prev_position) ASC')
                ->limit(500)
                ->get();
            return response()->json(['filter' => 'rank_movement', 'rows' => $movement], 200);
        }

        if ($filter === 'competitor_gaps') {
            $gapRows = DB::table('semrush_imports')
                ->where('import_batch', $maxBatch)
                ->where(function ($q) {
                    $q->whereNull('position')->orWhere('position', '>', 10);
                })
                ->select('sku_code', 'keyword', 'position', 'search_volume')
                ->orderBy('sku_code')
                ->get();
            $bySku = [];
            foreach ($gapRows as $row) {
                $code = (string) ($row->sku_code ?? '');
                if (!isset($bySku[$code])) {
                    $bySku[$code] = [];
                }
                $bySku[$code][] = [
                    'keyword' => $row->keyword,
                    'position' => $row->position,
                    'search_volume' => $row->search_volume,
                ];
            }
            return response()->json(['filter' => 'competitor_gaps', 'by_sku' => $bySku], 200);
        }

        $rows = DB::table('semrush_imports')
            ->select(
                'import_batch',
                DB::raw('COUNT(*) as row_count'),
                DB::raw('MIN(imported_by) as imported_by')
            )
            ->groupBy('import_batch')
            ->orderByDesc('import_batch')
            ->limit(12)
            ->get();

        if ($rows->isEmpty()) {
            return response()->json(['history' => []], 200);
        }

        $history = $rows->map(function ($row) {
            return [
                'import_batch' => $row->import_batch,
                'row_count'    => (int) $row->row_count,
                'imported_by'  => (string) ($row->imported_by ?? ''),
            ];
        })->values()->all();

        return response()->json(['history' => $history], 200);
    }

    /**
     * DELETE /api/admin/semrush-import/{batch_date}
     * SOURCE: openapi.yaml — DELETE /admin/semrush-import/{batch_date}; DB-04 audit_log insert only
     */
    public function delete(string $batchDate)
    {
        $user = auth()->user();
        if (!$user || !optional($user->role)->name || strtoupper($user->role->name) !== 'ADMIN') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $deleted = DB::table('semrush_imports')->where('import_batch', $batchDate)->delete();

        AuditLog::create([
            'entity_type' => 'semrush_import',
            'entity_id'   => $batchDate,
            'action'      => 'delete_batch',
            'field_name'  => null,
            'old_value'   => null,
            'new_value'   => json_encode(['batch_date' => $batchDate, 'rows_deleted' => $deleted]),
            'actor_id'    => (string) (auth()->id() ?? ''),
            'actor_role'  => optional(optional($user)->role)->name ?? '',
            'timestamp'   => now(),
        ]);

        return response()->json([
            'import_batch' => $batchDate,
            'rows_deleted' => $deleted,
        ], 200);
    }
}

