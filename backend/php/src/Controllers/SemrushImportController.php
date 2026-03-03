<?php
namespace App\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            return response()->json(['error' => 'Validation failed', 'message' => 'File is not a valid CSV.'], 422);
        }

        $file = $request->file('file');

        if (!$file->isValid()) {
            return response()->json(['error' => 'Validation failed', 'message' => 'File is not a valid CSV.'], 422);
        }

        $path = $file->getRealPath();
        if ($path === false) {
            return response()->json(['error' => 'Validation failed', 'message' => 'File is not a valid CSV.'], 422);
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return response()->json(['error' => 'Validation failed', 'message' => 'File is not a valid CSV.'], 422);
        }

        $header = fgetcsv($handle);
        if ($header === false || !is_array($header)) {
            fclose($handle);
            return response()->json(['error' => 'Validation failed', 'message' => 'File is not a valid CSV.'], 422);
        }

        $normalizedHeader = array_map(function ($h) {
            return strtolower(trim((string) $h));
        }, $header);

        $keywordIndex = array_search('keyword', $normalizedHeader, true);
        if ($keywordIndex === false) {
            fclose($handle);
            return response()->json(['error' => 'Validation failed', 'message' => 'Missing required Keyword column.'], 422);
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
                return response()->json(['error' => 'Validation failed', 'message' => 'File exceeds maximum row limit of 100000.'], 422);
            }

            $row = [];
            foreach ($normalizedHeader as $idx => $name) {
                $row[$name] = $data[$idx] ?? null;
            }
            $rows[] = $row;
        }

        fclose($handle);

        if ($rowCount === 0) {
            return response()->json(['error' => 'Validation failed', 'message' => 'File contains no data rows.'], 422);
        }

        $importBatch = now()->toDateString();

        $existing = DB::table('semrush_imports')->where('import_batch', $importBatch)->count();
        if ($existing > 0) {
            return response()->json(['error' => 'Validation failed', 'message' => 'An import for this batch date already exists.'], 422);
        }

        $username = (string) ($user->name ?? $user->email ?? 'system');

        $insertData = [];
        foreach ($rows as $row) {
            $insertData[] = [
                'import_batch'   => $importBatch,
                'keyword'        => (string) ($row['keyword'] ?? ''),
                'position'       => isset($row['position']) && $row['position'] !== '' ? (int) $row['position'] : null,
                'prev_position'  => isset($row['prev_position']) && $row['prev_position'] !== '' ? (int) $row['prev_position'] : null,
                'search_volume'  => isset($row['search_volume']) && $row['search_volume'] !== '' ? (int) $row['search_volume'] : null,
                'keyword_diff'   => isset($row['keyword_diff']) && $row['keyword_diff'] !== '' ? (int) $row['keyword_diff'] : null,
                'url'            => isset($row['url']) && $row['url'] !== '' ? (string) $row['url'] : null,
                'traffic_pct'    => isset($row['traffic_pct']) && $row['traffic_pct'] !== '' ? (float) $row['traffic_pct'] : null,
                'trend'          => isset($row['trend']) && $row['trend'] !== '' ? (string) $row['trend'] : null,
                'imported_by'    => $username,
                'imported_at'    => now(),
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
            'import_batch'   => $importBatch,
            'rows_imported'  => $rowCount,
            'keyword_count'  => $keywordCount,
        ], 200);
    }

    /**
     * GET /api/admin/semrush-import/latest
     */
    public function latest()
    {
        $user = auth()->user();
        if (!$user || !optional($user->role)->name || strtoupper($user->role->name) !== 'ADMIN') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $row = DB::table('semrush_imports')
            ->select('import_batch', DB::raw('COUNT(*) as row_count'))
            ->groupBy('import_batch')
            ->orderByDesc('import_batch')
            ->first();

        if (!$row) {
            return response()->json(['import_batch' => null, 'row_count' => 0], 200);
        }

        return response()->json([
            'import_batch' => $row->import_batch,
            'row_count'    => (int) $row->row_count,
        ], 200);
    }

    /**
     * DELETE /api/admin/semrush-import/{batch_date}
     */
    public function delete(string $batchDate)
    {
        $user = auth()->user();
        if (!$user || !optional($user->role)->name || strtoupper($user->role->name) !== 'ADMIN') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $deleted = DB::table('semrush_imports')->where('import_batch', $batchDate)->delete();

        return response()->json([
            'import_batch' => $batchDate,
            'rows_deleted' => $deleted,
        ], 200);
    }
}

