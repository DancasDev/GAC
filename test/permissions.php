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

test('purgePermissionsBy', function () use ($gac) {
    $gac->clearCache();
    $gac->purgePermissionsBy('user', [1]);
    assert(true);
});
