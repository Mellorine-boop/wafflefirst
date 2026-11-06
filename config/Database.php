<?php
namespace App\Config;

use PDO;
use PDOException;
use RuntimeException;

final class Database {
    public static function connect(): PDO {
        $host = getenv('WAFFLE_DB_HOST') ?: '127.0.0.1';
        $port = getenv('WAFFLE_DB_PORT') ?: '3306';
        $name = getenv('WAFFLE_DB_NAME') ?: 'waffle_sip_pos';
        $user = getenv('WAFFLE_DB_USER') ?: 'root';
        $pass = getenv('WAFFLE_DB_PASS') ?: '';

        try {
            return new PDO(
                "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Database connection failed. Import database/01_waffle_sip_schema_and_data.sql and check MySQL/MariaDB settings.'
            );
        }
    }
}
