<?php

namespace App\Support;

class Env
{
    private static ?array $values = null;

    public static function load(string $path): void
    {
        if (!is_readable($path)) {
            Logger::error("Env: .env file missing or unreadable at {$path}");
            throw new \RuntimeException(
                "Configuration error: .env file missing or unreadable at {$path}"
            );
        }

        $values = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if (
                strlen($value) >= 2
                && (
                    ($value[0] === '"' && str_ends_with($value, '"'))
                    || ($value[0] === "'" && str_ends_with($value, "'"))
                )
            ) {
                $value = substr($value, 1, -1);
            }

            $values[$key] = $value;
        }

        self::$values = $values;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$values === null) {
            Logger::error('Env::get called before Env::load — .env was never loaded');
            throw new \RuntimeException('Configuration error: environment not loaded');
        }

        return self::$values[$key] ?? $default;
    }
}
