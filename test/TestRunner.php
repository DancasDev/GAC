<?php
require_once __DIR__ . '/TestCase.php';

echo "=== GAC Test Suite ===\n\n";

// ─── Environment variable / CLI options with defaults ─────────────────────
$isInteractive = function_exists('posix_isatty') ? posix_isatty(STDIN) : false;

$driver = getenv('GAC_DB_DRIVER') ?: 'mysql';
$defaultPort = $driver === 'pgsql' ? '5432' : '3306';
$defaultUser = $driver === 'pgsql' ? 'postgres' : 'root';
$host   = getenv('GAC_DB_HOST')   ?: '127.0.0.1';
$port   = getenv('GAC_DB_PORT')   ?: $defaultPort;
$dbname = getenv('GAC_DB_NAME')   ?: 'gac_test';
$user   = getenv('GAC_DB_USER')   ?: $defaultUser;
$pass   = getenv('GAC_DB_PASS') !== false ? (string)getenv('GAC_DB_PASS') : '';

echo "Connecting to $driver://$user@$host:$port/$dbname ...\n";

try {
    TestCase::connect($driver, $host, (int) $port, $dbname, $user, $pass);
    echo "OK\n\n";
} catch (\Throwable $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// ─── Include test files ──────────────────────────────────────────────────
$testFiles = [
    'utils',
    'gac',
    'permissions',
    'result',
    'restrictions',
    'domain',
    'cache',
    'adapters',
    'granularity',
    'stress',
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

if ($__totalFailed > 0) {
    exit(1);
}
