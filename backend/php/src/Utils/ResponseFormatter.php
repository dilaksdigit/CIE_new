<?php
namespace App\Utils;

class ResponseFormatter {
    public static function format($data, $message = "Success", $status = 200) {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], $status);
    }

    public static function error($message = "Error", $status = 400, $errors = []) {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ], $status);
    }
}
