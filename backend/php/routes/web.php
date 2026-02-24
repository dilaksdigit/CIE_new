<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'CIE API',
        'version' => '2.3.2',
        'status' => 'running'
    ]);
});
