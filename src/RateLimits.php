<?php

namespace NineteenNinetyFour\SocialImages;

use Throwable;

/**
 * Whether a failed render was the screenshot service saying "too many".
 * Cloudflare's driver reports its error as text, so it's matched on that.
 */
class RateLimits
{
    public static function caused(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return (bool) preg_match('/(?<![\w-])429(?![\w-])/', $message)
            || str_contains($message, 'rate limit')
            || str_contains($message, 'too many requests')
            || str_contains($message, 'time limit');
    }
}
