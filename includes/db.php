<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env_or_default('DB_HOST', DB_HOST);
    $name = env_or_default('DB_NAME', DB_NAME);
    $user = env_or_default('DB_USER', DB_USER);
    $pass = env_or_default('DB_PASS', DB_PASS);
    $charset = env_or_default('DB_CHARSET', DB_CHARSET);

    $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

