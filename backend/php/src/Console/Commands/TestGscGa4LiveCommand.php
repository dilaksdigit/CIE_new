<?php

namespace App\Console\Commands;

use App\Services\BaselineService;
use App\Models\Sku;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

/**
 * Live GSC + GA4 verification (read-only except optional baseline smoke on no-data SKU).
 * SOURCE: CIE_Master_Developer_Build_Spec.docx §9–10; Self-Validation Pack 1B/1D.
 */
class TestGscGa4LiveCommand extends Command
{
    protected $signature = 'test:gsc-ga4-live';

    protected $description = 'Verify live GSC/GA4 auth, APIs, DB data, routes, and fail-soft baseline (PASS/FAIL checklist)';

    /** @var list<int> */
    private array $failedChecks = [];

    private bool $check26Skipped = false;

    /** @var array<string, mixed>|null */
    private ?array $serviceAccountJson = null;

    private ?string $accessToken = null;

    public function handle(): int
    {
        $this->printHeader();

        $this->section1();
        $this->section2();
        $this->section3();
        $this->section4();
        $this->section5();
        $this->section6();
        $this->section7();

        return $this->printSummary();
    }

    private function printHeader(): void
    {
        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║          CIE v2.3.2 — LIVE GSC + GA4 DATA TEST            ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->line('');
    }

    private function fail(int $num): void
    {
        $this->failedChecks[] = $num;
    }

    private function section1(): void
    {
        $this->line('SECTION 1: Environment & Auth');

        $gscProp = env('GSC_PROPERTY');
        $ok1 = is_string($gscProp) && trim($gscProp) !== '';
        $this->line($this->lineCheck(1, 'ENV — GSC_PROPERTY is set and non-empty', $ok1, $ok1 ? null : 'Set GSC_PROPERTY in .env'));
        if (!$ok1) {
            $this->fail(1);
        }

        $ga4Prop = env('GA4_PROPERTY_ID');
        $ok2 = is_string($ga4Prop) && trim($ga4Prop) !== '';
        $this->line($this->lineCheck(2, 'ENV — GA4_PROPERTY_ID is set and non-empty', $ok2, $ok2 ? null : 'Set GA4_PROPERTY_ID in .env'));
        if (!$ok2) {
            $this->fail(2);
        }

        $rawSa = env('GOOGLE_SERVICE_ACCOUNT_JSON');
        $ok3 = false;
        $err3 = 'Set GOOGLE_SERVICE_ACCOUNT_JSON to JSON or path to key file';
        if (is_string($rawSa) && trim($rawSa) !== '') {
            $parsed = $this->loadServiceAccountJson();
            if (is_array($parsed) && !empty($parsed['client_email'])) {
                $ok3 = true;
                $this->serviceAccountJson = $parsed;
            } else {
                $err3 = 'Invalid JSON or missing client_email';
            }
        }
        $this->line($this->lineCheck(3, 'ENV — GOOGLE_SERVICE_ACCOUNT_JSON valid (client_email)', $ok3, $ok3 ? null : $err3));
        if (!$ok3) {
            $this->fail(3);
        }

        $ok4 = false;
        $err4 = null;
        if ($this->serviceAccountJson !== null) {
            try {
                $this->assertJwtAndToken();
                $ok4 = $this->accessToken !== null;
            } catch (\Throwable $e) {
                $err4 = $e->getMessage();
            }
        }
        $this->line($this->lineCheck(4, 'AUTH — Service account access token', $ok4, $ok4 ? null : ($err4 ?? 'Could not obtain token')));
        if (!$ok4) {
            $this->fail(4);
        }

        $this->line('');
    }

    private function section2(): void
    {
        $this->line('SECTION 2: GSC Connection');

        $ok5 = false;
        $detail5 = '';
        $fix5 = null;
        $gscProp = (string) env('GSC_PROPERTY', '');
        if ($this->accessToken && $gscProp !== '') {
            try {
                $resp = Http::withToken($this->accessToken)
                    ->timeout(60)
                    ->get('https://www.googleapis.com/webmasters/v3/sites');
                if ($resp->successful()) {
                    $sites = $resp->json('siteEntry') ?? [];
                    foreach ($sites as $entry) {
                        $u = (string) ($entry['siteUrl'] ?? '');
                        if ($this->sitesMatch($gscProp, $u)) {
                            $ok5 = true;
                            $detail5 = 'property in site list';
                            break;
                        }
                    }
                    if (!$ok5) {
                        $fix5 = 'Add property to GSC or set GSC_PROPERTY to match siteUrl from sites.list';
                        $detail5 = 'GSC_PROPERTY not found in site list';
                    }
                } else {
                    $detail5 = 'HTTP '.$resp->status();
                    $fix5 = 'Grant service account access to Search Console';
                }
            } catch (\Throwable $e) {
                $detail5 = $e->getMessage();
                $fix5 = 'Check network and credentials';
            }
        } else {
            $detail5 = 'Skipped (no token or GSC_PROPERTY)';
            $fix5 = 'Fix Section 1';
        }
        $this->line($this->lineCheck(5, 'GSC API — site list includes GSC_PROPERTY', $ok5, $detail5, $fix5));
        if (!$ok5) {
            $this->fail(5);
        }

        $ok6 = false;
        $detail6 = '';
        $fix6 = null;
        $rowCount = 0;
        if ($this->accessToken && $gscProp !== '') {
            try {
                $end = now()->subDay()->format('Y-m-d');
                $start = now()->subDays(7)->format('Y-m-d');
                $enc = rawurlencode($gscProp);
                $resp = Http::withToken($this->accessToken)
                    ->timeout(90)
                    ->post(
                        "https://www.googleapis.com/webmasters/v3/sites/{$enc}/searchAnalytics/query",
                        [
                            'startDate' => $start,
                            'endDate' => $end,
                            'dimensions' => ['page'],
                            'rowLimit' => 5,
                        ]
                    );
                if ($resp->successful()) {
                    $rows = $resp->json('rows') ?? [];
                    $rowCount = is_array($rows) ? count($rows) : 0;
                    $ok6 = $rowCount > 0;
                    $detail6 = $ok6 ? "{$rowCount} row(s)" : 'empty rows';
                    if (!$ok6) {
                        $fix6 = 'Property may have no search data in the last 7 days, or permissions issue';
                    }
                } else {
                    $detail6 = 'HTTP '.$resp->status();
                    $fix6 = 'Verify service account has Full User on the property';
                }
            } catch (\Throwable $e) {
                $detail6 = $e->getMessage();
                $fix6 = 'Check API access';
            }
        } else {
            $detail6 = 'Skipped';
            $fix6 = 'Fix Section 1';
        }
        $this->line($this->lineCheck(6, 'GSC API — searchAnalytics last 7 days has rows', $ok6, $detail6, $fix6));
        if (!$ok6) {
            $this->fail(6);
        }

        $ok7 = false;
        $detail7 = '';
        $fix7 = null;
        if (Schema::hasTable('business_rules')) {
            $row = DB::table('business_rules')->where('rule_key', 'sync.gsc_cron_schedule')->first();
            if ($row && isset($row->value) && $this->isValidCronFiveField((string) $row->value)) {
                $ok7 = true;
                $detail7 = (string) $row->value;
            } else {
                $detail7 = $row ? 'invalid cron: '.($row->value ?? '') : 'rule_key missing';
                $fix7 = 'Seed sync.gsc_cron_schedule in business_rules';
            }
        } else {
            $detail7 = 'business_rules table missing';
            $fix7 = 'Run migrations';
        }
        $this->line($this->lineCheck(7, 'GSC CRON — sync.gsc_cron_schedule in DB', $ok7, $detail7, $fix7));
        if (!$ok7) {
            $this->fail(7);
        }

        $this->line('');
    }

    private function section3(): void
    {
        $this->line('SECTION 3: GSC Data in Database');

        $ok8 = Schema::hasTable('url_performance');
        $this->line($this->lineCheck(8, 'TABLE — url_performance exists', $ok8, $ok8 ? null : 'Run migrations'));
        if (!$ok8) {
            $this->fail(8);
        }

        $ok9 = false;
        $n9 = 0;
        if ($ok8) {
            $n9 = (int) DB::table('url_performance')->count();
            $ok9 = $n9 > 0;
        }
        $this->line($this->lineCheck(9, 'DATA — url_performance row count > 0', $ok9, $ok9 ? number_format($n9).' rows' : '0 rows'));
        if (!$ok9) {
            $this->fail(9);
        }

        $dateCol = $ok8 && Schema::hasColumn('url_performance', 'week_ending') ? 'week_ending' : 'window_end';
        $ok10 = false;
        $detail10 = '';
        if ($ok8 && Schema::hasColumn('url_performance', $dateCol)) {
            $cut = now()->subDays(14)->toDateString();
            $ok10 = DB::table('url_performance')->where($dateCol, '>=', $cut)->exists();
            $latest = DB::table('url_performance')->orderByDesc($dateCol)->value($dateCol);
            $detail10 = $latest ? (string) $latest : 'no dates';
        } else {
            $detail10 = 'date column missing';
        }
        $this->line($this->lineCheck(10, 'FRESH — data within last 14 days ('.$dateCol.')', $ok10, $detail10, $ok10 ? null : 'Run GSC weekly sync'));
        if (!$ok10) {
            $this->fail(10);
        }

        $ok11 = false;
        $detail11 = '';
        if ($ok8 && Schema::hasColumn('url_performance', 'sku_id')) {
            $d = (int) DB::table('url_performance')->whereNotNull('sku_id')->selectRaw('COUNT(DISTINCT sku_id) as c')->value('c');
            $ok11 = $d >= 3;
            $detail11 = $d.' distinct sku_id';
        } else {
            $detail11 = 'sku_id column missing';
        }
        $this->line($this->lineCheck(11, 'SKUS — ≥3 distinct sku_id in url_performance', $ok11, $detail11, $ok11 ? null : 'Backfill sku_id or run URL→SKU mapping'));
        if (!$ok11) {
            $this->fail(11);
        }

        $ok12 = false;
        if ($ok8) {
            $ok12 = DB::table('url_performance')
                ->whereNotNull('impressions')
                ->whereNotNull('clicks')
                ->whereNotNull('ctr')
                ->whereNotNull('avg_position')
                ->exists();
        }
        $this->line($this->lineCheck(12, 'FIELDS — impressions, clicks, ctr, avg_position NOT NULL', $ok12, $ok12 ? 'spot-check OK' : 'no complete row'));
        if (!$ok12) {
            $this->fail(12);
        }

        $this->line('');
    }

    private function section4(): void
    {
        $this->line('SECTION 4: GA4 Connection');

        $scriptPath = dirname(base_path()).DIRECTORY_SEPARATOR.'python'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'integrations'.DIRECTORY_SEPARATOR.'test_ga4_connection.py';
        $py = $this->detectPythonBinary();

        $ok13 = false;
        $ok14 = false;
        $detail = '';
        $fix = null;

        if (!is_file($scriptPath)) {
            $detail = 'Missing '.$scriptPath;
            $fix = 'Restore test_ga4_connection.py';
        } elseif ($py === null) {
            $detail = 'python / python3 not found on PATH';
            $fix = 'Install Python 3 and google-analytics-data';
        } else {
            $cwd = dirname(base_path());
            $result = Process::timeout(120)
                ->path($cwd)
                ->run([$py, $scriptPath]);

            $out = trim($result->output().$result->errorOutput());
            if ($result->successful() && str_starts_with($out, 'PASS|')) {
                $ok13 = true;
                $ok14 = true;
                $detail = substr($out, strlen('PASS|'));
            } elseif (str_starts_with($out, 'FAIL|')) {
                $detail = $out;
                $ok13 = false;
                $ok14 = false;
                $fix = 'Grant analytics.readonly / GA4 property access to service account';
            } else {
                $detail = $out !== '' ? $out : ('exit '.$result->exitCode());
                $fix = 'See Python error; ensure GA4_PROPERTY_ID and credentials';
            }
        }

        $this->line($this->lineCheck(13, 'GA4 API — BetaAnalyticsDataClient test query', $ok13, $ok13 ? 'connected' : $detail, $fix));
        if (!$ok13) {
            $this->fail(13);
        }

        $this->line($this->lineCheck(14, 'GA4 API — organic sessions > 0 (last 7 days)', $ok14, $detail, $fix));
        if (!$ok14) {
            $this->fail(14);
        }

        $ok15 = false;
        $detail15 = '';
        $fix15 = null;
        if (Schema::hasTable('business_rules')) {
            $row = DB::table('business_rules')->where('rule_key', 'sync.ga4_cron_schedule')->first();
            if ($row && isset($row->value) && $this->isValidCronFiveField((string) $row->value)) {
                $ok15 = true;
                $detail15 = (string) $row->value;
            } else {
                $detail15 = $row ? 'invalid cron' : 'rule_key missing';
                $fix15 = 'Seed sync.ga4_cron_schedule in business_rules';
            }
        } else {
            $detail15 = 'business_rules missing';
            $fix15 = 'Run migrations';
        }
        $this->line($this->lineCheck(15, 'GA4 CRON — sync.ga4_cron_schedule in DB', $ok15, $detail15, $fix15));
        if (!$ok15) {
            $this->fail(15);
        }

        $this->line('');
    }

    private function section5(): void
    {
        $this->line('SECTION 5: GA4 Data in Database');

        $ok16 = Schema::hasTable('ga4_landing_performance');
        $this->line($this->lineCheck(16, 'TABLE — ga4_landing_performance exists', $ok16, $ok16 ? null : 'Run migrations'));
        if (!$ok16) {
            $this->fail(16);
        }

        $ok17 = false;
        $n17 = 0;
        if ($ok16) {
            $n17 = (int) DB::table('ga4_landing_performance')->count();
            $ok17 = $n17 > 0;
        }
        $this->line($this->lineCheck(17, 'DATA — ga4_landing_performance row count > 0', $ok17, $ok17 ? number_format($n17).' rows' : '0 rows'));
        if (!$ok17) {
            $this->fail(17);
        }

        $ga4DateCol = $ok16 && Schema::hasColumn('ga4_landing_performance', 'week_ending') ? 'week_ending' : 'window_end';
        $ok18 = false;
        $detail18 = '';
        if ($ok16 && Schema::hasColumn('ga4_landing_performance', $ga4DateCol)) {
            $cut = now()->subDays(14)->toDateString();
            $ok18 = DB::table('ga4_landing_performance')->where($ga4DateCol, '>=', $cut)->exists();
            $latest = DB::table('ga4_landing_performance')->orderByDesc($ga4DateCol)->value($ga4DateCol);
            $detail18 = $latest ? (string) $latest : 'no dates';
        } else {
            $detail18 = 'date column missing';
        }
        $this->line($this->lineCheck(18, 'FRESH — data within last 14 days ('.$ga4DateCol.')', $ok18, $detail18, $ok18 ? null : 'Run GA4 weekly sync'));
        if (!$ok18) {
            $this->fail(18);
        }

        $ok19 = false;
        if ($ok16 && Schema::hasColumn('ga4_landing_performance', 'organic_sessions')
            && Schema::hasColumn('ga4_landing_performance', 'organic_conversion_rate')) {
            $ok19 = DB::table('ga4_landing_performance')
                ->whereNotNull('organic_sessions')
                ->whereNotNull('organic_conversion_rate')
                ->exists();
        }
        $this->line($this->lineCheck(19, 'FIELDS — organic_sessions, organic_conversion_rate NOT NULL', $ok19, $ok19 ? 'spot-check OK' : 'no row'));
        if (!$ok19) {
            $this->fail(19);
        }

        $ok20 = false;
        $detail20 = '';
        if ($ok16 && Schema::hasColumn('ga4_landing_performance', 'organic_sessions')
            && Schema::hasColumn('ga4_landing_performance', 'organic_conversions')
            && Schema::hasColumn('ga4_landing_performance', 'organic_conversion_rate')) {
            $row = DB::table('ga4_landing_performance')
                ->where('organic_sessions', '>', 10)
                ->whereNotNull('organic_conversion_rate')
                ->whereNotNull('organic_conversions')
                ->first();
            if ($row) {
                $sessions = (float) $row->organic_sessions;
                $conversions = (float) $row->organic_conversions;
                $manual = $sessions > 0 ? $conversions / $sessions : 0.0;
                $stored = (float) $row->organic_conversion_rate;
                $diff = abs($stored - $manual);
                $ok20 = $diff < 0.001;
                $detail20 = 'diff='.number_format($diff, 6);
            } else {
                $detail20 = 'no row with organic_sessions > 10';
            }
        } else {
            $detail20 = 'required columns missing';
        }
        $this->line($this->lineCheck(20, 'CALC — organic_conversion_rate ≈ conversions/sessions', $ok20, $detail20, $ok20 ? null : 'Rate should be CIE-computed from organic metrics'));
        if (!$ok20) {
            $this->fail(20);
        }

        $this->line('');
    }

    private function section6(): void
    {
        $this->line('SECTION 6: Baseline Capture');

        $ok21 = Schema::hasTable('gsc_baselines');
        $this->line($this->lineCheck(21, 'TABLE — gsc_baselines exists', $ok21, $ok21 ? null : 'Run migrations'));
        if (!$ok21) {
            $this->fail(21);
        }

        $routesFile = base_path('routes/api.php');
        $content = is_file($routesFile) ? (string) file_get_contents($routesFile) : '';
        $ok22 = str_contains($content, '/gsc/baseline/{sku_id}');
        $this->line($this->lineCheck(22, 'ENDPOINT — POST /api/v1/gsc/baseline/{sku_id} in routes', $ok22, $ok22 ? 'routes/api.php' : 'route missing'));
        if (!$ok22) {
            $this->fail(22);
        }

        $ok23 = str_contains($content, '/ga4/baseline/{sku_id}');
        $this->line($this->lineCheck(23, 'ENDPOINT — POST /api/v1/ga4/baseline/{sku_id} in routes', $ok23, $ok23 ? 'routes/api.php' : 'route missing'));
        if (!$ok23) {
            $this->fail(23);
        }

        $ok24 = false;
        $detail24 = '';
        if ($ok21) {
            $baseline = DB::table('gsc_baselines')
                ->whereNotNull('baseline_impressions')
                ->whereNotNull('baseline_avg_position')
                ->whereNotNull('baseline_organic_sessions')
                ->whereNotNull('baseline_conversion_rate')
                ->first();
            $ok24 = $baseline !== null;
            $detail24 = $ok24 ? 'baseline_id '.$baseline->id : 'no combined GSC+GA4 row';
        }
        $this->line($this->lineCheck(24, 'DATA — gsc_baselines row with GSC + GA4 columns', $ok24, $detail24, $ok24 ? null : 'Capture baselines after deploy pipeline'));
        if (!$ok24) {
            $this->fail(24);
        }

        $this->line('');
    }

    private function section7(): void
    {
        $this->line('SECTION 7: Error Handling');

        $ok25 = Schema::hasTable('gsc_unmatched_urls');
        $this->line($this->lineCheck(25, 'UNMATCHED — gsc_unmatched_urls table exists', $ok25, $ok25 ? null : 'Run migrations'));
        if (!$ok25) {
            $this->fail(25);
        }

        if (!Schema::hasTable('url_performance') || !Schema::hasColumn('url_performance', 'sku_id')) {
            $this->check26Skipped = true;
            $this->line('  ○ CHECK 26:  No-data SKU baseline fail-soft               SKIP');
            $this->line('              → Note: url_performance.sku_id not present — cannot select no-data SKU');
            $this->line('');

            return;
        }

        $sub = DB::table('url_performance')->whereNotNull('sku_id')->select('sku_id');
        $noDataSku = DB::table('skus')
            ->whereNotIn('id', $sub)
            ->value('id');

        if ($noDataSku === null) {
            $this->check26Skipped = true;
            $this->line('  ○ CHECK 26:  No-data SKU baseline fail-soft               SKIP');
            $this->line('              → Note: all SKUs have url_performance.sku_id (cannot pick no-data SKU)');
            $this->line('');
            return;
        }

        $ok26 = false;
        $err = null;
        try {
            $sku = Sku::find($noDataSku);
            if (!$sku) {
                $err = 'SKU not found';
            } else {
                /** @var BaselineService $svc */
                $svc = app(BaselineService::class);
                $svc->captureGsc($sku);
                $svc->captureGa4($sku);
                $ok26 = true;
            }
        } catch (\Throwable $e) {
            $err = $e->getMessage();
        }
        $this->line($this->lineCheck(26, 'FAILSOFT — baseline for SKU without url_performance data', $ok26, $ok26 ? 'sku_id '.$noDataSku : ($err ?? 'failed'), $ok26 ? null : 'BaselineService must not throw'));
        if (!$ok26) {
            $this->fail(26);
        }

        $this->line('');
    }

    private function printSummary(): int
    {
        $failed = array_values(array_unique($this->failedChecks));
        sort($failed);
        $total = 26;
        $skip = $this->check26Skipped ? 1 : 0;
        $pass = $total - count($failed) - $skip;

        $this->line('══════════════════════════════════════════════════════════════');
        if (count($failed) === 0) {
            $line = 'RESULT: '.$pass.'/'.$total.' PASS                              ✓ ALL CLEAR';
            if ($skip > 0) {
                $line .= '  (CHECK 26 skipped)';
            }
            $this->line($line);
        } else {
            $failLine = 'RESULT: '.$pass.'/'.$total.' PASS, '.count($failed).' FAIL                     ✗ ISSUES FOUND';
            if ($skip > 0) {
                $failLine .= '  (CHECK 26 skipped)';
            }
            $this->line($failLine);
            $this->line('Failed checks: '.implode(', ', $failed));
        }
        $this->line('══════════════════════════════════════════════════════════════');

        return count($failed) === 0 ? 0 : 1;
    }

    /**
     * @return array{0: string, 1: string} [symbol, padded label]
     */
    private function sym(bool $ok): array
    {
        return $ok ? ['✓', 'PASS'] : ['✗', 'FAIL'];
    }

    private function lineCheck(int $num, string $label, bool $ok, ?string $detail = null, ?string $fix = null): string
    {
        [$s, $status] = $this->sym($ok);
        $line = sprintf('  %s CHECK %d:  %-48s %s', $s, $num, $label, $status);
        if ($ok && $detail !== null && $detail !== '') {
            $line .= '  ('.$detail.')';
        }
        if (!$ok) {
            if ($detail !== null && $detail !== '') {
                $line .= "\n              → Error: ".$detail;
            }
            if ($fix !== null && $fix !== '') {
                $line .= "\n              → Fix: ".$fix;
            }
        }
        return $line;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadServiceAccountJson(): ?array
    {
        $raw = env('GOOGLE_SERVICE_ACCOUNT_JSON');
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $raw = trim($raw);
        if ($raw[0] === '{') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : null;
        }
        if (is_file($raw)) {
            $decoded = json_decode((string) file_get_contents($raw), true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    private function assertJwtAndToken(): void
    {
        if ($this->serviceAccountJson === null) {
            throw new \RuntimeException('No service account JSON');
        }
        $assertion = $this->createJwtAssertion($this->serviceAccountJson);
        $resp = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]);
        if (!$resp->successful()) {
            throw new \RuntimeException('Token '.$resp->status().': '.$resp->body());
        }
        $token = $resp->json('access_token');
        if (!is_string($token) || $token === '') {
            throw new \RuntimeException('No access_token in response');
        }
        $this->accessToken = $token;
    }

    /**
     * @param array<string, mixed> $sa
     */
    private function createJwtAssertion(array $sa): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $now = time();
        $scope = 'https://www.googleapis.com/auth/webmasters.readonly https://www.googleapis.com/auth/analytics.readonly';
        $claim = [
            'iss' => $sa['client_email'] ?? '',
            'scope' => $scope,
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];
        $h = $this->base64UrlEncode((string) json_encode($header, JSON_UNESCAPED_SLASHES));
        $c = $this->base64UrlEncode((string) json_encode($claim, JSON_UNESCAPED_SLASHES));
        $input = $h.'.'.$c;
        $pem = $sa['private_key'] ?? '';
        $key = openssl_pkey_get_private((string) $pem);
        if ($key === false) {
            throw new \RuntimeException('openssl: invalid private_key in service account JSON');
        }
        $signature = '';
        if (!openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('openssl_sign failed');
        }

        return $input.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function sitesMatch(string $configured, string $fromApi): bool
    {
        $a = rtrim($configured, '/');
        $b = rtrim($fromApi, '/');

        return strcasecmp($a, $b) === 0;
    }

    private function isValidCronFiveField(string $value): bool
    {
        $v = trim($value);
        if ($v === '') {
            return false;
        }
        if (preg_match('/^@(?:yearly|annually|monthly|weekly|daily|hourly|reboot)$/', $v)) {
            return true;
        }

        return (bool) preg_match('/^\S+\s+\S+\s+\S+\s+\S+\s+\S+$/', $v);
    }

    private function detectPythonBinary(): ?string
    {
        foreach (['python', 'python3'] as $bin) {
            $p = Process::path(dirname(base_path()))->run([$bin, '--version']);
            if ($p->successful()) {
                return $bin;
            }
        }

        return null;
    }
}
