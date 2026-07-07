<?php
echo "\n=== Domain ===\n";

TestCase::resetData();

$gac = TestCase::createGAC();
$r = $gac->getRestrictions(false);

// ── Domain allow ─────────────────────────────────────────────────────────────
test('domain allow: localhost en whitelist = permitido', function () use ($r) {
    assert($r->run(['domain' => ['host' => 'localhost']])->passed);
});

test('domain allow: admin.miepresa.com (wildcard) = permitido', function () use ($r) {
    assert($r->run(['domain' => ['host' => 'admin.miepresa.com']])->passed);
});

test('domain allow: api.miepresa.com (wildcard) = permitido', function () use ($r) {
    assert($r->run(['domain' => ['host' => 'api.miepresa.com']])->passed);
});

test('domain allow: sub.admin.miepresa.com (wildcard multinivel) = permitido', function () use ($r) {
    assert($r->run(['domain' => ['host' => 'sub.admin.miepresa.com']])->passed);
});

test('domain allow: ejemplo.com NO en whitelist = denegado', function () use ($r) {
    $res = $r->run(['domain' => ['host' => 'ejemplo.com']]);
    assert(!$res->passed && $res->rule === 'allow');
});

test('domain allow: host vacio = denegado', function () use ($r) {
    $res = $r->run(['domain' => ['host' => '']]);
    assert(!$res->passed && $res->type === 'domain');
});

test('domain allow: host sin string = denegado', function () use ($r) {
    $res = $r->run(['domain' => ['host' => 123]]);
    assert(!$res->passed && $res->type === 'domain');
});

// ── Domain deny via personal rule ────────────────────────────────────────────
test('domain deny via personal rule', function () {
    $pdo = TestCase::$pdo;
    $pdo->exec("DELETE FROM gac_restriction WHERE type='domain' AND entity_type='1' AND entity_id=1");
    $pdo->exec("INSERT INTO gac_restriction (entity_type, entity_id, scope_path, type, rule, config) VALUES
        ('1', 1, '*', 'domain', 'deny', '{\"list\":[\"baneado.ejemplo.com\"]}')");

    $gac = new \DancasDev\GAC\GAC($pdo);
    $gac->setEntity('user', 1)->setScope('*');
    $r2 = $gac->getRestrictions(false);

    $res = $r2->run(['domain' => ['host' => 'baneado.ejemplo.com']]);
    assert(!$res->passed && $res->rule === 'deny');

    assert($r2->run(['domain' => ['host' => 'admin.miepresa.com']])->passed);
});

// ── validateStructure ────────────────────────────────────────────────────────
test('validateStructure domain allow', function () {
    assert(\DancasDev\GAC\Restrictions\Restrictions::validateStructure('domain', 'allow', ['list' => ['*.miepresa.com']]));
});

test('validateStructure domain deny', function () {
    assert(\DancasDev\GAC\Restrictions\Restrictions::validateStructure('domain', 'deny', ['list' => ['baneado.ejemplo.com']]));
});

test('validateStructure domain list no array = false', function () {
    assert(!\DancasDev\GAC\Restrictions\Restrictions::validateStructure('domain', 'allow', ['list' => 'no-es-array']));
});

test('validateStructure domain rule invalida = false', function () {
    assert(!\DancasDev\GAC\Restrictions\Restrictions::validateStructure('domain', 'invalid_rule', ['list' => []]));
});

test('validateStructure domain sin list = false', function () {
    assert(!\DancasDev\GAC\Restrictions\Restrictions::validateStructure('domain', 'allow', []));
});
