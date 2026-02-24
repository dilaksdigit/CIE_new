<?php

namespace App\Support;

use App\Services\BusinessRulesService;

/**
 * Static access to business rules. Use BusinessRules::get('key') or BusinessRules::get('key', default).
 */
final class BusinessRules
{
    public static function get(string $key, $default = null): mixed
    {
        $service = app(BusinessRulesService::class);
        return $service->get($key, $default);
    }

    public static function all(): array
    {
        return app(BusinessRulesService::class)->all();
    }

    public static function invalidateCache(): void
    {
        app(BusinessRulesService::class)->invalidateCache();
    }
}
