<?php

namespace App\Support;

class Logger
{
    private static ?string $path = null;

    public static function configure(string $path): void
    {
        self::$path = $path;
    }

    public static function info(string $message): void
    {
        self::write('info', $message);
    }

    public static function warning(string $message): void
    {
        self::write('warning', $message);
    }

    public static function error(string $message): void
    {
        self::write('error', $message);
    }

    private static function write(string $level, string $message): void
    {
        $path = self::resolvePath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $line = sprintf('[%s] %s: %s%s', date('Y-m-d H:i:s'), strtoupper($level), $message, PHP_EOL);

        file_put_contents($path, $line, FILE_APPEND);
    }

    private static function resolvePath(): string
    {
        return self::$path ?? dirname(__DIR__, 2) . '/storage/logs/app.log';
    }
}
