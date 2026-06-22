<?php
require_once __DIR__ . '/TestCase.php';

echo "=== GAC Test Suite ===\n\n";

// ─── Interactive prompts with env fallback ───────────────────────────────
$driver = getenv('GAC_DB_DRIVER') ?: readline('Driver [mysql/pgsql] (default: mysql): ') ?: 'mysql';
$defaultPort = $driver === 'pgsql' ? '5432' : '3306';
$defaultUser = $driver === 'pgsql' ? 'postgres' : 'root';
$host   = getenv('GAC_DB_HOST')   ?: readline('Host [127.0.0.1]: ') ?: '127.0.0.1';
$port   = getenv('GAC_DB_PORT')   ?: readline("Port [$defaultPort]: ") ?: $defaultPort;
$dbname = getenv('GAC_DB_NAME')   ?: readline('Database [gac_test]: ') ?: 'gac_test';
$user   = getenv('GAC_DB_USER')   ?: readline("User [$defaultUser]: ") ?: $defaultUser;

$pass = getenv('GAC_DB_PASS');
if ($pass === false || $pass === '') {
    echo 'Password (enter = none): ';
    $pass = trim(fgets(STDIN));
}

echo "\nConnecting to $driver://$user@$host:$port/$dbname ...\n";

try {
    TestCase::connect($driver, $host, (int) $port, $dbname, $user, $pass);
    echo "OK\n\n";
} catch (\Throwable $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// ─── Include test files ──────────────────────────────────────────────────
$testFiles = [
    'gac',
    'permissions',
    'result',
    'restrictions',
    'cache',
    'adapters',
];

foreach ($testFiles as $file) {
    $path = __DIR__ . "/$file.php";
    if (!file_exists($path)) {
        echo "  $file.php not found\n";
        continue;
    }
    test_reset();
    require $path;
    test_section_report($file);
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  Total — Pasaron: $__totalPassed, Fallaron: $__totalFailed\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
