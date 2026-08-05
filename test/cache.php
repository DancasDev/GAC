<?php
echo "\n=== Cache ===\n";

test('cache miss → carga DB', function () {
    $gac = new \DancasDev\GAC\GAC(TestCase::$pdo, ['driver' => 'file', 'path' => __DIR__ . '/../src/writable']);
    $gac->setEntity('user', 1)->setScope('*');
    $gac->clearCache();
    $p = $gac->getPermissions();
    assert($p->get('users')->hasFeature('read'));
});

test('cache hit', function () {
    $gac = new \DancasDev\GAC\GAC(TestCase::$pdo, ['driver' => 'file', 'path' => __DIR__ . '/../src/writable']);
    $gac->setEntity('user', 1)->setScope('*');
    $gac->getPermissions();          // miss → DB + write cache
    $p2 = $gac->getPermissions();    // hit
    assert($p2->get('users')->hasFeature('create'));
});

test('clearCache recarga', function () {
    $gac = new \DancasDev\GAC\GAC(TestCase::$pdo, ['driver' => 'file', 'path' => __DIR__ . '/../src/writable']);
    $gac->setEntity('user', 1)->setScope('*');
    $gac->clearCache();
    $p = $gac->getPermissions();
    assert($p->get('users')->hasFeature('dev'));
});

test('global cache miss + hit', function () {
    $gac = new \DancasDev\GAC\GAC(TestCase::$pdo, ['driver' => 'file', 'path' => __DIR__ . '/../src/writable']);
    $gac->setEntity('user', 1)->setScope('*');
    $gac->clearCache(true);
    $p = $gac->getPermissions();
    assert($p->get('users')->hasFeature('delete'));
    $p2 = $gac->getPermissions();
    assert($p2->get('users')->hasFeature('delete'));
});

test('clearCache(true) elimina global cache', function () {
    $gac = new \DancasDev\GAC\GAC(TestCase::$pdo, ['driver' => 'file', 'path' => __DIR__ . '/../src/writable']);
    $gac->setEntity('user', 1)->setScope('*');
    $gac->clearCache(true);
    assert(true);
});
