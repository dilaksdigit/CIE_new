<?php
namespace App\Middleware;
class LoggingMiddleware {
    public function handle($request, $next) { return $next($request); }
}
