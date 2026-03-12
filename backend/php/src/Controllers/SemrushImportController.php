<?php
namespace App\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx Sections 3–5

class SemrushImportController
{
    /**
     * POST /api/admin/semrush-import
     */
    public function import(Request $request)
    {
        $user = auth()->user();
        if (!$user || !optional($user->role)->name || strtoupper($user->role->name) !== 'ADMIN') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'Validation failed', 'message' => 'File could not be read as CSV. Check the file opened correctly in Excel or a text editor. It must be a plain CSV, not an Excel .xlsx file.'], 422);
        }

        $file = $request->file('file');

        if (!$file->isValid()) {
            return response()->json(['error' => 'Validation failed', 'message' => 'File could not be read as CSV. Check the file opened correctly in Excel or a text editor. It must be a plain CSV, not an Excel .xlsx file.'], 422);
        }

        $path = $file->getRealPath();
        if ($path === false) {
            return response()->json(['error' => 'Validation failed', 'message' => 'File could not be read as CSV. Check the file opened correctly in Excel or a text editor. It must be a plain CSV, not an Excel .xlsx file.'], 422);
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return response()->json(['error' => 'Validation failed', 'message' => 'File could not be read as CSV. Check the file opened correctly in Excel or a text editor. It must be a plain CSV, not an Excel .xlsx file.'], 422);
        }

        $header = fgetcsv($handle);
        if ($header === false || !is_array($header)) {
            fclose($handle);
            return response()->json(['error' => 'Validation failed', 'message' => 'File could not be read as CSV. Check the file opened correctly in Excel or a text editor. It must be a plain CSV, not an Excel .xlsx file.'], 422);
        }

        $normalizedHeader = array_map(function ($h) {
            return strtolower(trim((string) $h));
        }, $header);

        $columnMap = [
            'keyword'              => 'keyword',
            'position'             => 'position',
            'previous position'    => 'prev_position',
            'search volume'        => 'search_volume',
            'keyword difficulty'   => 'keyword_difficulty',
            'cpc (usd)'            => 'cpc_usd',
            'url'                  => 'competitor_url',
            'traffic (%)'          => 'traffic_pct',
            'traffic volume'       => 'traffic_volume',
            'trends'               => 'trend',
            'timestamp'            => 'timestamp',
            'competitor position'  => 'competitor_position',
        ];

        $keywordIndex = array_search('keyword', $normalizedHeader, true);
        if ($keywordIndex === false) {
            fclose($handle);
            return response()->json(['error' => 'Validation failed', 'message' => 'Missing required column: Keyword. Check you exported from Organic Research → Positions, not a different Semrush report.'], 422);
        }

        $rows = [];
        $rowCount = 0;
        while (($data = fgetcsv($handle)) !== false) {
            if ($data === [null] || $data === false) {
                continue;
            }
            $rowCount++;
            if ($rowCount > 100000) {
                fclose($handle);
                return response()->json(['error' => 'Validation failed', 'message' => 'File contains more than 100,000 rows. Split the export into smaller files and import each separately.'], 422);
            }

            $row = [];
            foreach ($normalizedHeader as $idx => $name) {
                $row[$name] = $data[$idx] ?? null;
            }
            $remapped = [];
            foreach ($columnMap as $normalisedKey => $dbKey) {
                if (array_key_exists($normalisedKey, $row)) {
                    $remapped[$dbKey] = $row[$normalisedKey];
                }
            }
            $rows[] = $remapped;
        }

        fclose($handle);

        if ($rowCount === 0) {
            return response()->json(['error' => 'Validation failed', 'message' => 'The file contains no data rows. Export again from Semrush and try uploading the new file.'], 422);
        }

        // SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx Section 4.1 — import_batch from CSV Timestamp (first data row)
        $importBatch = null;
        $firstTs = isset($rows[0]['timestamp']) ? trim((string) $rows[0]['timestamp']) : '';
        if ($firstTs !== '') {
            $t = strtotime($firstTs);
            if ($t !== false) {
                $importBatch = date('Y-m-d', $t);
            }
        }
        if ($importBatch === null) {
            $importBatch = now()->toDateString();
        }

        $importBatchId = (string) Str::uuid();

        $existing = DB::table('semrush_imports')->where('import_batch', $importBatch)->count();
        if ($existing > 0) {
            return response()->json(['error' => 'Validation failed', 'message' => 'A batch for this date has already been imported. Delete the existing batch first if you want to re-import.'], 422);
        }

        $username = (string) ($user->name ?? $user->email ?? 'system');

        $insertData = [];
        foreach ($rows as $row) {
            if (empty(trim((string) ($row['keyword'] ?? '')))) {
                continue;
            }
            $insertData[] = [
                'import_batch'        => $importBatch,
                'import_batch_id'     => $importBatchId,
                'keyword'             => (string) ($row['keyword'] ?? ''),
                'position'            => isset($row['position']) && $row['position'] !== '' ? (int) $row['position'] : null,
                'prev_position'       => isset($row['prev_position']) && $row['prev_position'] !== '' ? (int) $row['prev_position'] : null,
                'search_volume'       => isset($row['search_volume']) && $row['search_volume'] !== '' ? (int) $row['search_volume'] : null,
                'intent'              => isset($row['intent']) && $row['intent'] !== '' ? (string) $row['intent'] : null,
                'sku_code'            => isset($row['sku_code']) && $row['sku_code'] !== '' ? (string) $row['sku_code'] : null,
                'cluster_id'          => isset($row['cluster_id']) && $row['cluster_id'] !== '' ? (string) $row['cluster_id'] : null,
                'keyword_difficulty'  => isset($row['keyword_difficulty']) && $row['keyword_difficulty'] !== '' ? (int) $row['keyword_difficulty'] : null,
                'competitor_url'      => isset($row['competitor_url']) && $row['competitor_url'] !== '' ? (string) $row['competitor_url'] : null,
                'traffic_pct'         => strlen(trim((string) ($row['traffic_pct'] ?? ''))) ? (float) str_replace('%', '', $row['traffic_pct']) : null,
                'trend'               => isset($row['trend']) && $row['trend'] !== '' ? (string) $row['trend'] : null,
                'competitor_position' => isset($row['competitor_position']) && $row['competitor_position'] !== '' ? (int) $row['competitor_position'] : null,
                'imported_by'         => $username,
                'imported_at'         => now(),
            ];
        }

        DB::table('semrush_imports')->insert($insertData);

        AuditLog::create([
            'entity_type' => 'semrush_import',
            'entity_id'   => null,
            'action'      => 'import',
            'field_name'  => 'import_batch',
            'old_value'   => null,
            'new_value'   => $importBatch,
            'actor_id'    => (string) (auth()->id() ?? ''),
            'actor_role'  => optional(optional($user)->role)->name ?? '',
            'timestamp'   => now(),
        ]);

        $keywordCount = count(array_unique(array_map(function ($row) {
            return (string) ($row['keyword'] ?? '');
        }, $rows)));

        return response()->json([
            'import_batch'    => $importBatch,
            'import_batch_id' => $importBatchId,
            'rows_imported'   => count($insertData),
            'keyword_count'   => $keywordCount,
        ], 200);
    }

    /**
     * GET /api/admin/semrush-import/latest
     * Query param: filter=quick_wins (optional) — returns rows matching Quick Wins: position 11–30, keyword_difficulty < 40, search_volume > 500, tier hero/support.
     */
    public function latest(Request $request)
    {
        $user = auth()->user();
        if (!$user || !optional($user->role)->name || strtoupper($user->role->name) !== 'ADMIN') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        if ($request->query('filter') === 'quick_wins') {
            $quickWins = DB::table('semrush_imports')
                ->whereRaw('import_batch = (SELECT MAX(import_batch) FROM semrush_imports)')
                ->whereNotNull('prev_position')
                ->whereColumn('position', '<', 'prev_position')
                ->orderByRaw('(prev_position - position) DESC')
                ->select('keyword', 'position', 'prev_position', 'search_volume', 'keyword_difficulty', 'sku_code')
                ->limit(500)
                ->get();
            return response()->json(['filter' => 'quick_wins', 'rows' => $quickWins], 200);
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

