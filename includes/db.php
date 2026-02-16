<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $databaseUrl = getenv('DATABASE_URL');

    if ($databaseUrl !== false && $databaseUrl !== '') {
        $parts = parse_url($databaseUrl);
        if ($parts === false || !isset($parts['host'], $parts['path'])) {
            throw new RuntimeException('Invalid DATABASE_URL format.');
        }

        $host = $parts['host'];
        $port = isset($parts['port']) ? (string) $parts['port'] : DB_PORT;
        $name = ltrim((string) $parts['path'], '/');
        $user = isset($parts['user']) ? urldecode((string) $parts['user']) : '';
        $pass = isset($parts['pass']) ? urldecode((string) $parts['pass']) : '';
    } else {
        $host = env_or_default('DB_HOST', DB_HOST);
        $port = env_or_default('DB_PORT', DB_PORT);
        $name = env_or_default('DB_NAME', DB_NAME);
        $user = env_or_default('DB_USER', DB_USER);
        $pass = env_or_default('DB_PASS', DB_PASS);
    }

    $sslmode = env_or_default('DB_SSLMODE', DB_SSLMODE);

    $dsn = "pgsql:host={$host};port={$port};dbname={$name};sslmode={$sslmode}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
