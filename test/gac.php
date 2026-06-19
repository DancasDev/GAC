<?php
echo "\n=== GAC ===\n";

test('constructor con PDO', function () {
    $gac = new \DancasDev\GAC\GAC(TestCase::$pdo);
    assert($gac instanceof \DancasDev\GAC\GAC);
});

test('constructor con cache array', function () {
    $gac = new \DancasDev\GAC\GAC(TestCase::$pdo, ['driver' => 'file', 'path' => __DIR__ . '/../src/writable']);
    assert($gac instanceof \DancasDev\GAC\GAC);
});

test('setEntity + setScope encadenan', function () {
    $gac = new \DancasDev\GAC\GAC(TestCase::$pdo);
    $gac->setEntity('user', 1)->setScope('empresaX/SucursalA');
    assert($gac instanceof \DancasDev\GAC\GAC);
});

test('getPermissions sin setEntity lanza excepcion', function () {
    $threw = false;
    try {
        $gac = new \DancasDev\GAC\GAC(TestCase::$pdo);
        $gac->getPermissions();
    } catch (\Throwable $e) {
        $threw = true;
    }
    assert($threw);
});

test('getRestrictions sin setEntity lanza excepcion', function () {
    $threw = false;
    try {
        $gac = new \DancasDev\GAC\GAC(TestCase::$pdo);
        $gac->getRestrictions();
    } catch (\Throwable $e) {
        $threw = true;
    }
    assert($threw);
});

test('setEntity cliente', function () {
    $gac = new \DancasDev\GAC\GAC(TestCase::$pdo);
    $gac->setEntity('client', 99);
    assert($gac instanceof \DancasDev\GAC\GAC);
});

test('scope exacto empresaX → feature=1', function () {
    $gac = new \DancasDev\GAC\GAC(TestCase::$pdo);
    $gac->setEntity('user', 1)->setScope('empresaX');
    $p = $gac->getPermissions();
    $u = $p->get('users');
    assert($u !== null);
    assert($u->hasFeature('create'));
    assert(!$u->hasFeature('read'));
});

test('scope wildcard empresaX/* → feature=7', function () {
    $gac = new \DancasDev\GAC\GAC(TestCase::$pdo);
    $gac->setEntity('user', 1)->setScope('empresaX/SucursalA');
    $p = $gac->getPermissions();
    $u = $p->get('users');
    assert($u !== null);
    assert($u->hasFeature('create'));
    assert($u->hasFeature('read'));
    assert(!$u->hasFeature('delete'));
});

test('scope fallback * → feature=63', function () {
    $gac = new \DancasDev\GAC\GAC(TestCase::$pdo);
    $gac->setEntity('user', 1)->setScope('otraEmpresa');
    $p = $gac->getPermissions();
    $u = $p->get('users');
    assert($u !== null);
    assert($u->hasFeature('delete'));
});
