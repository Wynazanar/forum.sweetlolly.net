<?php

$pdo = null;

/**
 * PDO-подключение к БД.
 * Ожидает константы DB_HOST, DB_NAME, DB_USER, DB_PASS
 * (их задаёт migration.php или public/index.php).
 */
function db(): PDO
{
    global $pdo;

    if ($pdo === null) {
        if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
            throw new RuntimeException('DB constants are not defined. Load .env and app/Config/database.php first.');
        }

        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (Throwable $e) {
            http_response_code(500);
            die('Ошибка подключения к БД: ' . $e->getMessage());
        }
    }

    return $pdo;
}