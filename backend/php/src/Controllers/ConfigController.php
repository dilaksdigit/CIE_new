<?php

namespace App\Controllers;

use App\Support\BusinessRules;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * CIE config API — GET/PUT system configuration (gate thresholds, tier weights, etc.).
 * Stored in storage/app/cie_config.json. Admin-only for PUT.
 */
class ConfigController
{
    private function configPath(): string
    {
        return storage_path('app/cie_config.json');
    }

    public function index()
    {
        $path = $this->configPath();
        if (!File::exists($path)) {
            return ResponseFormatter::format([]);
        }

        $config = json_decode(File::get($path), true) ?: [];
        return ResponseFormatter::format($config);
    }

    public function update(Request $request)
    {
        $path = $this->configPath();
        $existing = [];
        if (File::exists($path)) {
            $existing = json_decode(File::get($path), true) ?: [];
        }

        $merged = array_merge($existing, $request->all());
        File::put($path, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return ResponseFormatter::format($merged);
    }
}
