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
}
