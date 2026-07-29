<?php
// ─── Simple PSR-4 autoloader ───────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $prefix = 'DancasDev\\GAC\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($path)) require $path;
});

// ─── Global test function ──────────────────────────────────────────────────
$__testPassed = 0;
$__testFailed = 0;
$__testErrors = [];
$__totalPassed = 0;
$__totalFailed = 0;

function test(string $name, callable $fn): void {
    global $__testPassed, $__testFailed, $__testErrors;
    try {
        $fn();
        echo "  \u{2705} $name\n";
        $__testPassed++;
    } catch (\Throwable $e) {
        echo "  \u{274C} $name: " . $e->getMessage() . "\n";
        $__testFailed++;
        $__testErrors[] = "$name: " . $e->getMessage();
    }
}

function test_section_report(string $section): void {
    global $__testPassed, $__testFailed, $__totalPassed, $__totalFailed;
    echo "  \u{2500}\u{2500} $section: $__testPassed passed, $__testFailed failed\n";
    $__totalPassed += $__testPassed;
    $__totalFailed += $__testFailed;
}

function test_reset(): void {
    global $__testPassed, $__testFailed, $__testErrors;
    $__testPassed = 0;
    $__testFailed = 0;
    $__testErrors = [];
}

// ─── TestCase base ─────────────────────────────────────────────────────────
use DancasDev\GAC\GAC;
use DancasDev\GAC\Schema;

class TestCase {
    static PDO $pdo;
    static string $driver;
    static string $dbname;
    private static bool $seeded = false;

    static function connect(string $driver, string $host, int $port, string $dbname, string $user, string $pass): void {
        self::$driver = $driver;
        self::$dbname = $dbname;

        $dsn = "$driver:host=$host;port=$port";
        $tmp = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        if ($driver === 'pgsql') {
            $tmp->exec("CREATE DATABASE \"$dbname\"");
        } else {
            $tmp->exec("CREATE DATABASE IF NOT EXISTS `$dbname` COLLATE utf8mb4_unicode_ci");
        }
        $tmp = null;

        $dsnDb = "$driver:host=$host;port=$port;dbname=$dbname";
        self::$pdo = new PDO($dsnDb, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        if ($driver === 'mysql') {
            self::$pdo->exec("SET NAMES utf8mb4");
            self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        }

        if (!self::$seeded) {
            if ($driver === 'mysql') {
                $stmt = self::$pdo->query("SHOW TABLES LIKE 'gac\_%'");
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $t) {
                    self::$pdo->exec("DROP TABLE IF EXISTS `$t`");
                }
            } else {
                $stmt = self::$pdo->query("SELECT tablename FROM pg_catalog.pg_tables WHERE tablename LIKE 'gac\_%'");
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $t) {
                    self::$pdo->exec("DROP TABLE IF EXISTS \"$t\"");
                }
            }
            Schema::install(self::$pdo);
            self::seed();
            self::$seeded = true;
        }
    }

    static function resetData(): void {
        self::exec("DELETE FROM gac_restriction");
        self::exec("DELETE FROM gac_role_entity");
        self::exec("DELETE FROM gac_permission");
        self::exec("DELETE FROM gac_role");
        self::exec("DELETE FROM gac_module");
        self::exec("DELETE FROM gac_module_category");
        self::seed();
    }

    private static function exec(string $sql): void {
        self::$pdo->exec(self::$driver === 'pgsql' ? str_replace('`', '"', $sql) : $sql);
    }

    private static function ins(string $table, string $columns, string $values): void {
        $sql = self::$driver === 'pgsql'
            ? "INSERT INTO \"$table\" ($columns) VALUES $values ON CONFLICT DO NOTHING"
            : "INSERT IGNORE INTO `$table` ($columns) VALUES $values";
        self::$pdo->exec($sql);
    }

    private static function seed(): void {
        $pdo = self::$pdo;
        $d = self::$driver;

        $ins = function (string $table, string $columns, string $values) use ($d): void {
            $sql = $d === 'pgsql'
                ? "INSERT INTO \"$table\" ($columns) VALUES $values ON CONFLICT DO NOTHING"
                : "INSERT IGNORE INTO `$table` ($columns) VALUES $values";
            self::$pdo->exec($sql);
        };

        $ins('gac_module_category', 'id, code', "(1, 'system'), (2, 'user'), (3, 'directories')");
        $ins('gac_module', 'id, module_category_id, code, is_developing',
            "(1, 2, 'my_profile', '0'), (2, 2, 'my_user', '0'), (3, 1, 'users', '0'), "
            . "(4, 1, 'user_access', '0'), (5, 1, 'roles', '0'), "
            . "(6, 3, 'directory_people', '0'), (7, 1, 'clients', '0')");
        $ins('gac_role', 'id', "(1), (2)");
        $ins('gac_permission', 'from_entity_type, from_entity_id, to_entity_type, to_entity_id, scope_path, feature, level',
            "('0', 1, '0', 1, '*', 63, '1'), ('0', 1, '0', 2, '*', 63, '1'), ('0', 1, '0', 3, '*', 2, '1'), ('0', 2, '0', 1, '*', 2, '1')");
        $ins('gac_permission', 'from_entity_type, from_entity_id, to_entity_type, to_entity_id, scope_path, feature, level',
            "('1', 1, '1', 3, 'empresaX', 1, '1'), ('1', 1, '1', 3, 'empresaX/*', 7, '1')");
        $ins('gac_role_entity', 'role_id, entity_type, entity_id, priority',
            "(1, '1', 1, 0), (2, '1', 2, 0)");
        $ins('gac_restriction', 'entity_type, entity_id, scope_path, type, rule, config',
            "('3', 0, '*', 'date', 'in_range', '{\"sd\":\"%Y-%M-%D 08:00\",\"ed\":\"%Y-%M-%D 18:00\"}'), "
            . "('3', 0, '*', 'ip', 'allow', '{\"list\":[\"192.168.1.*\",\"10.0.*.*\"]}'), "
            . "('3', 0, '*', 'domain', 'allow', '{\"list\":[\"*.miepresa.com\",\"localhost\"]}')");
    }

    static function createGAC(): GAC {
        $gac = new GAC(self::$pdo);
        $gac->setEntity('user', 1)->setScope('*');
        return $gac;
    }
}
