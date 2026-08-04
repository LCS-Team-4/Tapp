<?php

namespace App\Database;

use PDO;

class Connection
{
    private static ?PDO $instance = null;

    public static function get(): PDO
    {
        if (self::$instance === null) {
            $config = config('database');

            self::$instance = new PDO(
                $config['dsn'],
                $config['user'],
                $config['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        }

        return self::$instance;
    }
}
