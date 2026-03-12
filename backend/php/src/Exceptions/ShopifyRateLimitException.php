<?php
// SOURCE: Task E2 Validation Report — Item 6
// SOURCE: CLAUDE.md Section 10

namespace App\Exceptions;

use RuntimeException;

class ShopifyRateLimitException extends RuntimeException
{
    public function __construct(
        string $message = 'Shopify rate limit exceeded after maximum retries',
        int $code = 429,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
