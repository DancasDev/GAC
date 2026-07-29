<?php
echo "\n=== Native Adapters ===\n";

use DancasDev\GAC\Drivers\Database\MysqliConnection;
use DancasDev\GAC\Drivers\Database\PgsqlConnection;

// Solo probamos el adapter nativo del driver en uso
$currentDriver = TestCase::$driver;

// ─── MySQLi ──────────────────────────────────────────────────────────────────

if ($currentDriver === 'mysql') :

test('MysqliConnection param', function () {
    $c = new mysqli('127.0.0.1', 'root', '', TestCase::$dbname, 3306);
    if ($c->connect_error) throw new \Exception($c->connect_error);
    $a = new MysqliConnection($c);
    assert($a->param() === '?');
    assert($a->param(0) === '?');
    assert($a->param(5) === '?');
    $c->close();
});

test('MysqliConnection prepare + execute + fetchAll', function () {
    $c = new mysqli('127.0.0.1', 'root', '', TestCase::$dbname, 3306);
    if ($c->connect_error) throw new \Exception($c->connect_error);
    $a = new MysqliConnection($c);
    $s = $a->prepare('SELECT COUNT(*) as n FROM gac_role');
    assert($s !== false);
    $s->execute();
    $r = $s->fetchAll();
    assert((int)$r[0]['n'] >= 1);
    $c->close();
});

test('MysqliConnection prepare with params', function () {
    $c = new mysqli('127.0.0.1', 'root', '', TestCase::$dbname, 3306);
    if ($c->connect_error) throw new \Exception($c->connect_error);
    $a = new MysqliConnection($c);
    $s = $a->prepare('SELECT id FROM gac_role WHERE id = ' . $a->param());
    $s->execute([1]);
    $r = $s->fetchAll();
    assert(count($r) === 1);
    assert((int)$r[0]['id'] === 1);
    $c->close();
});

test('MysqliConnection exec', function () {
    $c = new mysqli('127.0.0.1', 'root', '', TestCase::$dbname, 3306);
    if ($c->connect_error) throw new \Exception($c->connect_error);
    $a = new MysqliConnection($c);
    $r = $a->exec('DO 1');
    assert($r !== false);
    $c->close();
});

test('MysqliConnection temp table roundtrip', function () {
    $c = new mysqli('127.0.0.1', 'root', '', TestCase::$dbname, 3306);
    if ($c->connect_error) throw new \Exception($c->connect_error);
    $a = new MysqliConnection($c);
    $a->exec('CREATE TEMPORARY TABLE _gac_t (id INT, label VARCHAR(20))');
    $a->exec("INSERT INTO _gac_t VALUES (1, 'a'), (2, 'b')");
    $s = $a->prepare('SELECT label FROM _gac_t WHERE id = ' . $a->param());
    $s->execute([2]);
    $r = $s->fetchAll();
    assert($r[0]['label'] === 'b');
    $c->close();
});

endif;

// ─── PostgreSQL (native) ─────────────────────────────────────────────────────

if ($currentDriver === 'pgsql') :

test('PgsqlConnection param', function () {
    if (!function_exists('pg_connect')) throw new \Exception('ext-pgsql not loaded');
    $c = @pg_connect('host=127.0.0.1 port=5432 dbname=' . TestCase::$dbname . ' user=postgres');
    if ($c === false) throw new \Exception('pg_connect failed');
    $a = new PgsqlConnection($c);
    assert($a->param() === '$1');
    assert($a->param() === '$2');
    assert($a->param(0) === '$1');
    assert($a->param() === '$3');
    pg_close($c);
});

test('PgsqlConnection prepare + execute + fetchAll', function () {
    if (!function_exists('pg_connect')) throw new \Exception('ext-pgsql not loaded');
    $c = @pg_connect('host=127.0.0.1 port=5432 dbname=' . TestCase::$dbname . ' user=postgres');
    if ($c === false) throw new \Exception('pg_connect failed');
    $a = new PgsqlConnection($c);
    $s = $a->prepare('SELECT COUNT(*) as n FROM gac_role');
    assert($s !== false);
    $s->execute();
    $r = $s->fetchAll();
    assert((int)$r[0]['n'] >= 1);
    pg_close($c);
});

test('PgsqlConnection prepare with params', function () {
    if (!function_exists('pg_connect')) throw new \Exception('ext-pgsql not loaded');
    $c = @pg_connect('host=127.0.0.1 port=5432 dbname=' . TestCase::$dbname . ' user=postgres');
    if ($c === false) throw new \Exception('pg_connect failed');
    $a = new PgsqlConnection($c);
    $s = $a->prepare('SELECT id FROM gac_role WHERE id = ' . $a->param());
    $s->execute([1]);
    $r = $s->fetchAll();
    assert(count($r) === 1);
    assert((int)$r[0]['id'] === 1);
    pg_close($c);
});

test('PgsqlConnection exec', function () {
    if (!function_exists('pg_connect')) throw new \Exception('ext-pgsql not loaded');
    $c = @pg_connect('host=127.0.0.1 port=5432 dbname=' . TestCase::$dbname . ' user=postgres');
    if ($c === false) throw new \Exception('pg_connect failed');
    $a = new PgsqlConnection($c);
    $r = $a->exec('SELECT 1');
    assert($r !== false);
    pg_close($c);
});

test('PgsqlConnection temp table roundtrip', function () {
    if (!function_exists('pg_connect')) throw new \Exception('ext-pgsql not loaded');
    $c = @pg_connect('host=127.0.0.1 port=5432 dbname=' . TestCase::$dbname . ' user=postgres');
    if ($c === false) throw new \Exception('pg_connect failed');
    $a = new PgsqlConnection($c);
    $a->exec('CREATE TEMPORARY TABLE _gac_t (id INT, label VARCHAR(20))');
    $a->exec("INSERT INTO _gac_t VALUES (1, 'a'), (2, 'b')");
    $s = $a->prepare('SELECT label FROM _gac_t WHERE id = ' . $a->param());
    $s->execute([2]);
    $r = $s->fetchAll();
    assert($r[0]['label'] === 'b');
    pg_close($c);
});

endif;

// ─── FileCache (siempre se ejecuta) ──────────────────────────────────────────

$tmpDir = sys_get_temp_dir() . '/gac_test_' . uniqid();

test('FileCache save + get', function () use ($tmpDir) {
    $c = new \DancasDev\GAC\Drivers\Cache\FileCache($tmpDir);
    assert($c->save('k1', 'hello', 60) === true);
    assert($c->get('k1') === 'hello');
});

test('FileCache get miss', function () use ($tmpDir) {
    $c = new \DancasDev\GAC\Drivers\Cache\FileCache($tmpDir);
    assert($c->get('nonexistent') === null);
});

test('FileCache delete', function () use ($tmpDir) {
    $c = new \DancasDev\GAC\Drivers\Cache\FileCache($tmpDir);
    $c->save('kdel', 'value', 60);
    assert($c->delete('kdel') === true);
    assert($c->get('kdel') === null);
});

// cleanup
$c = new \DancasDev\GAC\Drivers\Cache\FileCache($tmpDir);
$c->clean();
rmdir($tmpDir);
