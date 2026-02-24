<?php
namespace App\Middleware;
class RateLimitMiddleware {
    public function handle($request, $next) { return $next($request); }
}
