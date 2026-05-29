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

    /** Cache table-column availability once per import to avoid per-row information_schema lookups. */
    private function resolveSemrushImportColumns(): array
    {
        return [
            'keyword_diff' => Schema::hasColumn('semrush_imports', 'keyword_diff'),
            'keyword_difficulty' => Schema::hasColumn('semrush_imports', 'keyword_difficulty'),
            'url' => Schema::hasColumn('semrush_imports', 'url'),
            'competitor_url' => Schema::hasColumn('semrush_imports', 'competitor_url'),
            'sku_code' => Schema::hasColumn('semrush_imports', 'sku_code'),
            'intent' => Schema::hasColumn('semrush_imports', 'intent'),
            'cluster_id' => Schema::hasColumn('semrush_imports', 'cluster_id'),
            'competitor_position' => Schema::hasColumn('semrush_imports', 'competitor_position'),
        ];
    }

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
            // Core columns (support both "space" and "underscore" naming variants)
            'keyword'              => 'keyword',
            'position'             => 'position',
            'previous position'    => 'prev_position',
            'previous_position'    => 'prev_position',
            'prev_position'        => 'prev_position',
            'search volume'        => 'search_volume',
            'search_volume'        => 'search_volume',
            'keyword difficulty'   => 'keyword_diff',
            'keyword_difficulty'   => 'keyword_diff',
            'keyword diff'         => 'keyword_diff',
            'keyword_diff'         => 'keyword_diff',
            'url'                  => 'url',
            'traffic (%)'          => 'traffic_pct',
            'traffic_percent'      => 'traffic_pct',
            'traffic_pct'          => 'traffic_pct',
            'trends'               => 'trend',
            'trend'                => 'trend',
            'timestamp'            => 'timestamp',

            // Spec columns used downstream by writer suggestion filtering/grouping
            'sku code'             => 'sku_code',
            'sku_code'             => 'sku_code',
            'sku'                  => 'sku_code',
            'sku id'               => 'sku_code',
            'sku_id'               => 'sku_code',
            'product sku'          => 'sku_code',
            'product_sku'          => 'sku_code',
            'product id'           => 'sku_code',
            'product_id'           => 'sku_code',
            'item sku'             => 'sku_code',
            'item_sku'             => 'sku_code',
            'intent'               => 'intent',
            'cluster id'           => 'cluster_id',
            'cluster_id'           => 'cluster_id',
            'competitor url'       => 'competitor_url',
            'competitor_url'       => 'competitor_url',
            'competitor position'  => 'competitor_position',
            'competitor_position'  => 'competitor_position',
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
        $columns = $this->resolveSemrushImportColumns();
        $insertData = [];
        foreach ($remappedRows as $row) {
            $insertData[] = $this->buildSemrushInsertRow($row, $importBatch, $importBatchId, $importedByUsername, $columns);
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
    private function buildSemrushInsertRow(array $row, string $importBatch, string $importBatchId, string $username, array $columns): array
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
            // intentionally omitted from payload so CURRENT_TIMESTAMP default is used.
        ];

        if (!empty($columns['sku_code'])) {
            $rawSkuCode = isset($row['sku_code']) ? trim((string) $row['sku_code']) : '';
            // Normalize for reliable joins/lookups across CSV formatting differences.
            $base['sku_code'] = $rawSkuCode !== '' ? strtoupper($rawSkuCode) : null;
        }
        if (!empty($columns['intent'])) {
            $base['intent'] = isset($row['intent']) && trim((string) $row['intent']) !== ''
                ? (string) $row['intent']
                : null;
        }
        if (!empty($columns['cluster_id'])) {
            $base['cluster_id'] = isset($row['cluster_id']) && trim((string) $row['cluster_id']) !== ''
                ? (string) $row['cluster_id']
                : null;
        }
        if (!empty($columns['competitor_position'])) {
            $base['competitor_position'] = isset($row['competitor_position']) && $row['competitor_position'] !== ''
                ? (int) $row['competitor_position']
                : null;
        }

        $kd = isset($row['keyword_diff']) && $row['keyword_diff'] !== '' ? (int) $row['keyword_diff'] : null;
        if (!empty($columns['keyword_diff'])) {
            $base['keyword_diff'] = $kd;
        }
        if (!empty($columns['keyword_difficulty'])) {
            $base['keyword_difficulty'] = $kd;
        }

        $urlVal = isset($row['url']) && $row['url'] !== '' ? (string) $row['url'] : null;
        if (!empty($columns['url'])) {
            $base['url'] = $urlVal;
        }
        if (!empty($columns['competitor_url'])) {
            $base['competitor_url'] = isset($row['competitor_url']) && trim((string) $row['competitor_url']) !== ''
                ? (string) $row['competitor_url']
                : $urlVal;
        }

        return $base;
    }
}
