<?php
// SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx §4.1, §4.2; CIE_Master_Developer_Build_Spec.docx §2 — parser separated from controller
namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SemrushParserService
{
    private const ERR_CSV_READ = 'File could not be read as CSV. Check the file is a standard Semrush export.';
    private const ERR_KEYWORD_HEADER = 'Missing required column: Keyword. Check this is a Semrush Organic Positions export.';
    private const ERR_NO_ROWS = 'File contains no data rows. Export a non-empty report from Semrush.';
    private const ERR_TOO_MANY_ROWS = 'File contains too many rows. Split into smaller exports or contact your dev.';
    private const MAX_ROWS = 100000;

    /**
     * SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx §4.2 — checks 1–4 in order; additional checks; then duplicate batch (check 5)
     */
    public function parseAndValidate(UploadedFile $file, string $importedByUsername): SemrushParseResult
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return new SemrushParseResult(false, self::ERR_CSV_READ);
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return new SemrushParseResult(false, self::ERR_CSV_READ);
        }

        $header = fgetcsv($handle);
        if ($header === false || !is_array($header)) {
            fclose($handle);
            return new SemrushParseResult(false, self::ERR_CSV_READ);
        }

        $normalizedHeader = array_map(static function ($h) {
            return strtolower(trim((string) $h));
        }, $header);

        // Check 2 — Keyword column
        if (!in_array('keyword', $normalizedHeader, true)) {
            fclose($handle);
            return new SemrushParseResult(false, self::ERR_KEYWORD_HEADER);
        }

        $rows = [];
        $rowCount = 0;
        while (($data = fgetcsv($handle)) !== false) {
            if ($data === [null] || $data === false) {
                continue;
            }
            $rowCount++;
            // Check 4 — row count < 100,000
            if ($rowCount > self::MAX_ROWS) {
                fclose($handle);
                return new SemrushParseResult(false, self::ERR_TOO_MANY_ROWS);
            }

            $row = [];
            foreach ($normalizedHeader as $idx => $name) {
                $row[$name] = $data[$idx] ?? null;
            }
            $rows[] = $row;
        }
        fclose($handle);

        // Check 3 — row count > 0
        if ($rowCount === 0) {
            return new SemrushParseResult(false, self::ERR_NO_ROWS);
        }

        // SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx §4.1 — additional: Timestamp column required for batch dating
        if (!in_array('timestamp', $normalizedHeader, true)) {
            return new SemrushParseResult(false, 'Missing required column: Timestamp. Check this is a Semrush Organic Positions export.');
        }

        // SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx §4.1 — canonical columns only
        $columnMap = [
            'keyword'              => 'keyword',
            'position'             => 'position',
            'previous position'    => 'prev_position',
            'search volume'        => 'search_volume',
            'keyword difficulty'   => 'keyword_diff',
            'url'                  => 'url',
            'traffic (%)'          => 'traffic_pct',
            'trends'               => 'trend',
            'timestamp'            => 'timestamp',
        ];

        $remappedRows = [];
        foreach ($rows as $row) {
            $remapped = [];
            foreach ($columnMap as $normalisedKey => $dbKey) {
                if (array_key_exists($normalisedKey, $row)) {
                    $remapped[$dbKey] = $row[$normalisedKey];
                }
            }
            $remappedRows[] = $remapped;
        }

        // SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx Section 4.1
        // import_batch derived from first row Timestamp only.
        // Section 4.2 does not include cross-row date consistency check.
        $firstRow = $remappedRows[0];
        $ts = isset($firstRow['timestamp']) ? trim((string) $firstRow['timestamp']) : '';
        if ($ts === '') {
            return new SemrushParseResult(false, 'File contains a row with an empty Timestamp. Every row must include a Timestamp for batch dating.');
        }
        $t = strtotime($ts);
        if ($t === false) {
            return new SemrushParseResult(false, self::ERR_CSV_READ);
        }
        $importBatch = date('Y-m-d', $t);

        // Check 5 — no duplicate import_batch
        $existing = DB::table('semrush_imports')->where('import_batch', $importBatch)->count();
        if ($existing > 0) {
            return new SemrushParseResult(false, 'A Semrush import for '.$importBatch.' already exists. Delete the existing batch in the admin panel before re-importing.');
        }

        $rowErrors = [];
        $rowNum = 0;
        foreach ($remappedRows as $r) {
            ++$rowNum;
            if (trim((string) ($r['keyword'] ?? '')) === '') {
                $rowErrors[] = "Row {$rowNum}: Keyword field is empty.";
            }
        }
        if ($rowErrors !== []) {
            return new SemrushParseResult(false, 'File rejected. Fix these issues and re-upload.', $rowErrors);
        }

        $importBatchId = (string) Str::uuid();
        $insertData = [];
        foreach ($remappedRows as $row) {
            $insertData[] = $this->buildSemrushInsertRow($row, $importBatch, $importBatchId, $importedByUsername);
        }

        return new SemrushParseResult(
            true,
            null,
            [],
            $insertData,
            $importBatch,
            $importBatchId,
            $remappedRows
        );
    }

    /**
     * SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx §3.1 — map logical fields to physical columns (keyword_diff, url; legacy columns supported)
     */
    private function buildSemrushInsertRow(array $row, string $importBatch, string $importBatchId, string $username): array
    {
        $base = [
            'import_batch'    => $importBatch,
            'import_batch_id' => $importBatchId,
            'keyword'         => (string) ($row['keyword'] ?? ''),
            'position'        => isset($row['position']) && $row['position'] !== '' ? (int) $row['position'] : null,
            'prev_position'   => isset($row['prev_position']) && $row['prev_position'] !== '' ? (int) $row['prev_position'] : null,
            'search_volume'   => isset($row['search_volume']) && $row['search_volume'] !== '' ? (int) $row['search_volume'] : null,
            'traffic_pct'     => strlen(trim((string) ($row['traffic_pct'] ?? ''))) ? (float) str_replace('%', '', (string) $row['traffic_pct']) : null,
            'trend'           => isset($row['trend']) && $row['trend'] !== '' ? (string) $row['trend'] : null,
            'imported_by'     => $username,
            // SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx §3.1 — DB default owns imported_at
        ];

        $kd = isset($row['keyword_diff']) && $row['keyword_diff'] !== '' ? (int) $row['keyword_diff'] : null;
        if (Schema::hasColumn('semrush_imports', 'keyword_diff')) {
            $base['keyword_diff'] = $kd;
        }
        if (Schema::hasColumn('semrush_imports', 'keyword_difficulty')) {
            $base['keyword_difficulty'] = $kd;
        }

        $urlVal = isset($row['url']) && $row['url'] !== '' ? (string) $row['url'] : null;
        if (Schema::hasColumn('semrush_imports', 'url')) {
            $base['url'] = $urlVal;
        }
        if (Schema::hasColumn('semrush_imports', 'competitor_url')) {
            $base['competitor_url'] = $urlVal;
        }

        return $base;
    }
}
