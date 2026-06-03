<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * SOURCE: P0 — production must not expose stack traces or env values to API clients.
     */
    public function render($request, Throwable $e)
    {
        if (app()->environment(['production', 'staging'])) {
            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            if ($status < 400 || $status > 599) {
                $status = 500;
            }
            return response()->json([
                'error' => $status >= 500 ? 'Server error' : 'Request failed',
                'message' => $status >= 500
                    ? 'An unexpected error occurred. Contact the system administrator.'
                    : ($e->getMessage() ?: 'Request could not be completed.'),
            ], $status);
        }

        return parent::render($request, $e);
    }
}
