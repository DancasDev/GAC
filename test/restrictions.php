<?php
echo "\n=== Restrictions ===\n";

TestCase::resetData();

$gac = TestCase::createGAC();
$r = $gac->getRestrictions(false);

// ── Date ────────────────────────────────────────────────────────────────────
test('getRestrictions devuelve Restrictions con date', function () use ($r) {
    assert($r instanceof \DancasDev\GAC\Restrictions\Restrictions);
    assert($r->has('date'));
});

test('in_range 08-18: 10:00 = permitido', function () use ($r) {
    $h10 = strtotime(date('Y-m-d 10:00:00'));
    assert($r->run(['date' => ['timestamp' => $h10]])->passed);
});

test('in_range 08-18: 22:00 = denegado', function () use ($r) {
    $h22 = strtotime(date('Y-m-d 22:00:00'));
    assert(!$r->run(['date' => ['timestamp' => $h22]])->passed);
});

test('in_range 08-18: 07:59 = denegado', function () use ($r) {
    $h8 = strtotime(date('Y-m-d 07:59:00'));
    assert(!$r->run(['date' => ['timestamp' => $h8]])->passed);
});

test('in_range 08-18: 08:00 = permitido (borde)', function () use ($r) {
    $h8 = strtotime(date('Y-m-d 08:00:00'));
    assert($r->run(['date' => ['timestamp' => $h8]])->passed);
});

test('in_range 08-18: 18:00 = permitido (borde)', function () use ($r) {
    $h18 = strtotime(date('Y-m-d 18:00:00'));
    assert($r->run(['date' => ['timestamp' => $h18]])->passed);
});

// ── IP ──────────────────────────────────────────────────────────────────────
test('ip allow: 192.168.1.50 en whitelist = permitido', function () use ($r) {
    assert($r->run(['ip' => ['ip' => '192.168.1.50']])->passed);
});

test('ip allow: 200.0.0.1 NO en whitelist = denegado', function () use ($r) {
    $res = $r->run(['ip' => ['ip' => '200.0.0.1']]);
    assert(!$res->passed && $res->rule === 'allow');
});

test('ip allow: 10.0.0.99 (wildcard 10.0.*.*) = permitido', function () use ($r) {
    assert($r->run(['ip' => ['ip' => '10.0.0.99']])->passed);
});

test('ip invalida = denegado', function () use ($r) {
    $res = $r->run(['ip' => ['ip' => 'not-an-ip']]);
    assert(!$res->passed && $res->type === 'ip');
});

test('ip deny via personal rule', function () {
    $pdo = TestCase::$pdo;
    $pdo->exec("DELETE FROM gac_restriction WHERE type='ip' AND entity_type='1' AND entity_id=1");
    $pdo->exec("INSERT INTO gac_restriction (entity_type, entity_id, scope_path, type, rule, config) VALUES
        ('1', 1, '*', 'ip', 'deny', '{\"list\":[\"10.0.5.*\"]}')");

    $gac = new \DancasDev\GAC\GAC($pdo);
    $gac->setEntity('user', 1)->setScope('*');
    $r2 = $gac->getRestrictions(false);

    $res = $r2->run(['ip' => ['ip' => '10.0.5.99']]);
    assert(!$res->passed && $res->rule === 'deny');

    assert($r2->run(['ip' => ['ip' => '10.0.6.1']])->passed);
});

// ── validateStructure ───────────────────────────────────────────────────────
test('validateStructure date before valido', function () {
    assert(\DancasDev\GAC\Restrictions\Restrictions::validateStructure('date', 'before', ['d' => '2026-01-01']));
});

test('validateStructure date before sin d = false', function () {
    assert(!\DancasDev\GAC\Restrictions\Restrictions::validateStructure('date', 'before', []));
});

test('validateStructure date in_range con sd y ed = true', function () {
    assert(\DancasDev\GAC\Restrictions\Restrictions::validateStructure('date', 'in_range', ['sd' => '%Y-%M-%D 08:00', 'ed' => '%Y-%M-%D 18:00']));
});

test('validateStructure date in_range sin ed = false', function () {
    assert(!\DancasDev\GAC\Restrictions\Restrictions::validateStructure('date', 'in_range', ['sd' => '%Y-%M-%D 08:00']));
});

test('validateStructure rule invalida = false', function () {
    assert(!\DancasDev\GAC\Restrictions\Restrictions::validateStructure('date', 'invalid_rule', ['d' => 'x']));
});

test('validateStructure handler no registrado = false', function () {
    assert(!\DancasDev\GAC\Restrictions\Restrictions::validateStructure('unknown_type', 'foo', []));
});

test('validateStructure ip allow', function () {
    assert(\DancasDev\GAC\Restrictions\Restrictions::validateStructure('ip', 'allow', ['list' => ['192.168.1.*']]));
});

test('validateStructure ip deny', function () {
    assert(\DancasDev\GAC\Restrictions\Restrictions::validateStructure('ip', 'deny', ['list' => ['10.0.0.1']]));
});

test('validateStructure ip list no array = false', function () {
    assert(!\DancasDev\GAC\Restrictions\Restrictions::validateStructure('ip', 'allow', ['list' => 'not-array']));
});

// ── Combinaciones ───────────────────────────────────────────────────────────
test('after: 2027-01-01 > 2026-01-01 = permitido', function () {
    $pdo = TestCase::$pdo;
    $pdo->exec("DELETE FROM gac_restriction WHERE type='date' AND entity_type='1' AND entity_id=1");
    $pdo->exec("INSERT INTO gac_restriction (entity_type, entity_id, scope_path, type, rule, config) VALUES
        ('1', 1, 'empresaX/*', 'date', 'after', '{\"d\":\"2026-01-01\"}')");

    $gac = new \DancasDev\GAC\GAC($pdo);
    $gac->setEntity('user', 1)->setScope('empresaX');
    $r2 = $gac->getRestrictions(false);
    assert($r2->run(['date' => ['timestamp' => strtotime('2027-01-01')]])->passed);
});

test('before: 2026-06-01 < 2027-01-01 = permitido', function () {
    $pdo = TestCase::$pdo;
    $pdo->exec("DELETE FROM gac_restriction WHERE type='date' AND entity_type='3'");
    $pdo->exec("INSERT INTO gac_restriction (entity_type, entity_id, scope_path, type, rule, config) VALUES
        ('3', 0, 'empresaX/*', 'date', 'before', '{\"d\":\"2027-01-01\"}')");

    $gac = new \DancasDev\GAC\GAC($pdo);
    $gac->setEntity('user', 2)->setScope('empresaX/SucursalA');
    $r2 = $gac->getRestrictions(false);
    assert($r2->run(['date' => ['timestamp' => strtotime('2026-06-01')]])->passed);
});

// ── Prioridad ───────────────────────────────────────────────────────────────
test('personal out_range gana sobre rol before', function () {
    $pdo = TestCase::$pdo;
    $pdo->exec("DELETE FROM gac_restriction");
    $pdo->exec("INSERT INTO gac_restriction (entity_type, entity_id, scope_path, type, rule, config) VALUES
        ('1', 1, '*', 'date', 'out_range', '{\"sd\":\"%Y-%M-%D 12:00\",\"ed\":\"%Y-%M-%D 14:00\"}'),
        ('0', 1, '*', 'date', 'before', '{\"d\":\"2025-01-01\"}')");

    $gac = new \DancasDev\GAC\GAC($pdo);
    $gac->setEntity('user', 1)->setScope('*');
    $r2 = $gac->getRestrictions(false);

    // out_range permite 10:00 (fuera de 12-14)
    $res = $r2->run(['date' => ['timestamp' => strtotime(date('Y-m-d 10:00:00'))]]);
    assert($res->passed, 'out_range permite 10:00, rol before no deberia intervenir');

    // out_range deniega 13:00
    $res = $r2->run(['date' => ['timestamp' => strtotime(date('Y-m-d 13:00:00'))]]);
    assert(!$res->passed && $res->rule === 'out_range', 'out_range deniega 13:00');
});

// ── Purge ───────────────────────────────────────────────────────────────────
test('purgeRestrictionsBy global', function () {
    $gac = TestCase::createGAC();
    $gac->clearCache();
    $gac->purgeRestrictionsBy('global');
    assert(true);
});

test('purgeRestrictionsBy role', function () {
    $gac = TestCase::createGAC();
    $gac->clearCache();
    $gac->purgeRestrictionsBy('role', [1]);
    assert(true);
});
