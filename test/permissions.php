<?php
echo "\n=== Permissions ===\n";

$gac = TestCase::createGAC();

test('getPermissions devuelve Permissions', function () use ($gac) {
    $p = $gac->getPermissions();
    assert($p instanceof \DancasDev\GAC\Permissions\Permissions);
});

test('admin tiene create+read+update+delete+trash+dev en users', function () use ($gac) {
    $p = $gac->getPermissions();
    $u = $p->get('users');
    assert($u !== null);
    assert($u->hasFeature('create') && $u->hasFeature('read') && $u->hasFeature('update')
        && $u->hasFeature('delete') && $u->hasFeature('trash') && $u->hasFeature('dev'));
});

test('admin NO tiene create en directory_people', function () use ($gac) {
    $p = $gac->getPermissions();
    $dp = $p->get('directory_people');
    assert($dp !== null);
    assert($dp->hasFeature('read') && !$dp->hasFeature('create'));
});

test('supervisor solo read en users, sin my_profile', function () {
    $gac = new \DancasDev\GAC\GAC(TestCase::$pdo);
    $gac->setEntity('user', 2)->setScope('*');
    $p = $gac->getPermissions();
    $u = $p->get('users');
    assert($u !== null);
    assert(!$u->hasFeature('create'));
    assert($u->hasFeature('read'));
    assert($p->get('my_profile') === null);
});

test('getPermissionList sin filtro tiene users', function () use ($gac) {
    $list = $gac->getPermissionList();
    assert(is_array($list['users']) && count($list['users']) >= 3);
});

test('getPermissionList("*") feature=63', function () use ($gac) {
    assert($gac->getPermissionList('*')['users']['f'] === 63);
});

test('getPermissionList("empresaX") feature=1', function () use ($gac) {
    assert($gac->getPermissionList('empresaX')['users']['f'] === 1);
});

test('getPermissionList("empresaX/SucursalA") feature=7', function () use ($gac) {
    assert($gac->getPermissionList('empresaX/SucursalA')['users']['f'] === 7);
});

test('purgeCacheBy user returns true and removes cache', function () {
    $gac = new \DancasDev\GAC\GAC(TestCase::$pdo, ['driver' => 'file', 'path' => __DIR__ . '/../src/writable']);
    $gac->setEntity('user', 1)->setScope('*');
    $gac->getPermissions();
    $key = $gac->getCacheKey();
    assert(is_array($gac->cacheAdapter->get($key)));
    assert($gac->purgeCacheBy('user', [1]) === true);
    assert($gac->cacheAdapter->get($key) === null);
});

test('purgeCacheBy without cache returns false', function () {
    $gac = new \DancasDev\GAC\GAC(TestCase::$pdo);
    $gac->setEntity('user', 1)->setScope('*');
    assert($gac->purgeCacheBy('user', [1]) === false);
});

test('purgeCacheBy empty entityIds returns false', function () {
    $gac = new \DancasDev\GAC\GAC(TestCase::$pdo, ['driver' => 'file', 'path' => __DIR__ . '/../src/writable']);
    assert($gac->purgeCacheBy('user', []) === false);
});

// ── Permission unit tests ──────────────────────────────────────────────────
test('Permission hasFeature named features', function () {
    $p = new \DancasDev\GAC\Permissions\Permission(['f' => 5]); // create + update
    assert($p->hasFeature('create'));
    assert(!$p->hasFeature('read'));
    assert($p->hasFeature('update'));
    assert(!$p->hasFeature('delete'));
    assert(!$p->hasFeature('trash'));
    assert(!$p->hasFeature('dev'));
});

test('Permission hasFeature with array (AND)', function () {
    $p = new \DancasDev\GAC\Permissions\Permission(['f' => 3]); // create + read
    assert($p->hasFeature(['create', 'read']));
    assert(!$p->hasFeature(['create', 'delete']));
});

test('Permission hasFeature empty input returns false', function () {
    $p = new \DancasDev\GAC\Permissions\Permission(['f' => 63]);
    assert(!$p->hasFeature(''));
    assert(!$p->hasFeature([]));
});

test('Permission hasFeature unknown name returns false', function () {
    $p = new \DancasDev\GAC\Permissions\Permission(['f' => 63]);
    assert(!$p->hasFeature('nonexistent'));
});

test('Permission hasFeature feature=0 has no features', function () {
    $p = new \DancasDev\GAC\Permissions\Permission(['f' => 0]);
    assert(!$p->hasFeature('create'));
    assert(!$p->hasFeature('read'));
    assert(!$p->hasFeature('dev'));
});

test('Permission getFeature', function () {
    assert((new \DancasDev\GAC\Permissions\Permission(['f' => 63]))->getFeature() === 63);
    assert((new \DancasDev\GAC\Permissions\Permission(['f' => 0]))->getFeature() === null);
    assert((new \DancasDev\GAC\Permissions\Permission([]))->getFeature() === null);
});

test('Permission getPayload sin payload = null', function () {
    assert((new \DancasDev\GAC\Permissions\Permission(['f' => 1]))->getPayload() === null);
});

test('Permission getPayload devuelve array', function () {
    $p = new \DancasDev\GAC\Permissions\Permission(['f' => 1, 'p' => ['locker_ids' => [1, 2, 3]]]);
    assert($p->getPayload() === ['locker_ids' => [1, 2, 3]]);
});

test('payload de DB llega al Permission', function () use ($gac) {
    $p = $gac->getPermissions();
    $roles = $p->get('roles');
    assert($roles !== null);
    assert($roles->getPayload() === ['locker_ids' => [1, 2, 3]]);
    assert($p->get('users')->getPayload() === null);
});
