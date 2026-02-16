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

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $dsn = "pgsql:host={$host};port={$port};dbname={$name};sslmode={$sslmode}";

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        $isSupabaseDirectHost = str_ends_with($host, '.supabase.co') && str_starts_with($host, 'db.');
        $isNetworkFamilyError = stripos($e->getMessage(), 'Cannot assign requested address') !== false;

        if (!$isSupabaseDirectHost || !$isNetworkFamilyError) {
            throw $e;
        }

        // Supabase direct hosts can be IPv6-only. Retry using IPv4 pooler endpoints.
        $regions = [
            'us-east-1',
            'us-west-1',
            'us-west-2',
            'ca-central-1',
            'eu-west-1',
            'eu-west-2',
            'eu-west-3',
            'eu-central-1',
            'eu-central-2',
            'eu-north-1',
            'ap-south-1',
            'ap-southeast-1',
            'ap-southeast-2',
            'ap-northeast-1',
            'ap-northeast-2',
            'ap-east-1',
            'sa-east-1',
            'me-south-1',
        ];

        $projectRef = null;
        if (preg_match('/^db\.([a-z0-9]+)\.supabase\.co$/i', $host, $matches) === 1) {
            $projectRef = $matches[1];
        }
        $userCandidates = [$user];
        if ($projectRef !== null) {
            $userCandidates[] = "postgres.{$projectRef}";
        }
        $userCandidates = array_values(array_unique($userCandidates));

        $lastException = $e;
        foreach ($regions as $region) {
            $poolerHost = "aws-0-{$region}.pooler.supabase.com";
            $poolerDsn = "pgsql:host={$poolerHost};port=6543;dbname={$name};sslmode={$sslmode}";
            foreach ($userCandidates as $candidateUser) {
                try {
                    $pdo = new PDO($poolerDsn, $candidateUser, $pass, $options);
                    break 2;
                } catch (PDOException $poolerException) {
                    $lastException = $poolerException;
                }
            }
        }

        if (!$pdo instanceof PDO) {
            throw $lastException;
        }
    }

    $pdo->exec('SET search_path TO phpapp,public');

    return $pdo;
}
