<?php

namespace App\Support;

class Hmac
{
    public static function sign(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    public static function verify(string $payload, string $signature, string $secret): bool
    {
        return hash_equals(self::sign($payload, $secret), $signature);
    }
}
