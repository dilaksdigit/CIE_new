<?php
namespace App\Controllers;

use App\Models\Sku;
use App\Services\TitleEngineService;
use App\Utils\ResponseFormatter;
use Illuminate\Http\Request;

class TitleController {
    public function generate(Request $request, $sku_id) {
        $sku = Sku::findOrFail($sku_id);
        $service = new TitleEngineService();
        $result = $service->generate($sku);
        
        return ResponseFormatter::format($result, "Titles generated using Intent-first engine (L4)");
    }
}
