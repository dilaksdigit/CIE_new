<?php
namespace App\Utils;

use Illuminate\Http\JsonResponse;

class ResponseFormatter {
    /**
     * SOURCE: openapi.yaml ValidationResponse, ENF§7.2 — POST /api/v1/sku/{id}/validate returns this shape at JSON root (no success/data envelope).
     */
    public static function validationResponse(array $body, int $httpStatus = 200)
    {
        return response()->json($body, $httpStatus);
    }

    public static function format($data, $message = "Success", $status = 200) {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'timestamp' => gmdate('Y-m-d H:i:s')
        ], $status);
    }

    public static function error($message = "Error", $status = 400, $errors = []) {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
            'timestamp' => gmdate('Y-m-d H:i:s')
        ], $status);
    }

    /**
     * SOURCE: CLAUDE.md §10 — consistent JSON error envelope for API clients (no new endpoints).
     * FIX: API-16 — shared shape: machine code + human message (optional extra keys).
     */
    public static function standardError(int $httpStatus, string $errorCode, string $message, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'error' => $errorCode,
            'message' => $message,
        ], $extra), $httpStatus);
    }
}
