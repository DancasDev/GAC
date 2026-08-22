<?php
echo "\n=== Granularity & Dynamic Scope Inheritance ===\n";

TestCase::resetData();
$pdo = TestCase::$pdo;

// Setup custom modules, roles, and entities for business use cases
// Modulo 10: caja_chica
// Modulo 11: documentos
// Rol 10: Custodio de Caja Chica (permiso sobre caja_chica con scope_path = NULL)
// Rol 11: Gestor Documental (permiso sobre documentos con scope_path = "/docs/legal/*,/docs/publico")
// Usuario 10: Juan (asignado Rol 10 con scope_path = "/sucursal/caracas,/sucursal/maracaibo")
// Usuario 11: Pedro (asignado Rol 10 con scope_path = NULL -> fallback global '*')
// Usuario 12: Maria (asignado Rol 11 con scope_path = NULL)

$pdo->exec("INSERT INTO gac_module (id, module_category_id, code, is_developing, is_disabled) VALUES
    (10, 1, 'caja_chica', '0', '0'),
    (11, 1, 'documentos', '0', '0')");

$pdo->exec("INSERT INTO gac_role (id, is_disabled) VALUES (10, '0'), (11, '0')");

// Permiso para Rol 10: caja_chica, feature=7 (create+read+update), scope_path = NULL
$pdo->exec("INSERT INTO gac_permission (module_id, entity_type, entity_id, scope_path, feature, level, is_disabled) VALUES
    (10, '0', 10, NULL, 7, '1', '0')");

// Permiso para Rol 11: documentos, feature=3 (create+read), scope_path = '/docs/legal/*,/docs/publico'
$pdo->exec("INSERT INTO gac_permission (module_id, entity_type, entity_id, scope_path, feature, level, is_disabled) VALUES
    (11, '0', 11, '/docs/legal/*,/docs/publico', 3, '1', '0')");

// Vincular Usuario 10 con Rol 10 con scope_path = "/sucursal/caracas,/sucursal/maracaibo"
$pdo->exec("INSERT INTO gac_role_entity (role_id, entity_type, entity_id, scope_path, priority, is_disabled) VALUES
    (10, '1', 10, '/sucursal/caracas,/sucursal/maracaibo', 0, '0')");

// Vincular Usuario 11 con Rol 10 con scope_path = NULL (sin scope en entidad -> global '*')
$pdo->exec("INSERT INTO gac_role_entity (role_id, entity_type, entity_id, scope_path, priority, is_disabled) VALUES
    (10, '1', 11, NULL, 0, '0')");

// Vincular Usuario 12 con Rol 11 con scope_path = "/otro_scope" (el rol define su propio scope estático, no debe sobreescribirse)
$pdo->exec("INSERT INTO gac_role_entity (role_id, entity_type, entity_id, scope_path, priority, is_disabled) VALUES
    (11, '1', 12, '/otro_scope', 0, '0')");

// ── Tests de Herencia Dinámica ──────────────────────────────────────────────

test('Herencia Dinamica: Usuario 10 tiene acceso a caja_chica en /sucursal/caracas', function () use ($pdo) {
    $gac = new \DancasDev\GAC\GAC($pdo);
    $gac->setEntity('user', 10)->setScope('/sucursal/caracas');
    assert($gac->can('caja_chica', 'read') === true);
    assert($gac->can('caja_chica', 'create') === true);
    assert($gac->can('caja_chica', 'update') === true);
    assert($gac->can('caja_chica', 'delete') === false);
});

test('Herencia Dinamica: Usuario 10 tiene acceso a caja_chica en /sucursal/maracaibo (multi-path)', function () use ($pdo) {
    $gac = new \DancasDev\GAC\GAC($pdo);
    $gac->setEntity('user', 10)->setScope('/sucursal/maracaibo');
    assert($gac->can('caja_chica', 'read') === true);
});

test('Cero Brechas (Menor Privilegio): Usuario 10 NO tiene acceso a /sucursal/valencia ni /sucursal/3', function () use ($pdo) {
    $gac = new \DancasDev\GAC\GAC($pdo);
    $gac->setEntity('user', 10);
    assert($gac->setScope('/sucursal/valencia')->can('caja_chica', 'read') === false);
    assert($gac->setScope('/sucursal/3')->can('caja_chica', 'read') === false);
    assert($gac->setScope('*')->can('caja_chica', 'read') === false);
});

test('Herencia Dinamica NULL + NULL: Usuario 11 tiene acceso global (*)', function () use ($pdo) {
    $gac = new \DancasDev\GAC\GAC($pdo);
    $gac->setEntity('user', 11)->setScope('/cualquier/sucursal');
    assert($gac->can('caja_chica', 'read') === true);
});

test('Multi-path estatico de rol: Usuario 12 respeta paths definidos en rol (/docs/legal/* y /docs/publico)', function () use ($pdo) {
    $gac = new \DancasDev\GAC\GAC($pdo);
    $gac->setEntity('user', 12);
    // /docs/legal/* (wildcard matching)
    assert($gac->setScope('/docs/legal/contratos/2026')->can('documentos', 'read') === true);
    // /docs/publico (exact matching)
    assert($gac->setScope('/docs/publico')->can('documentos', 'read') === true);
    // No en scope
    assert($gac->setScope('/docs/privado')->can('documentos', 'read') === false);
    assert($gac->setScope('/otro_scope')->can('documentos', 'read') === false);
});

// ── Tests de Prioridad y Restricciones sobre Herencia Dinámica ──────────────

test('Restriccion bloquea acceso concedido dinamicamente', function () use ($pdo) {
    // Restriccion IP para Usuario 10 en /sucursal/caracas
    $pdo->exec("INSERT INTO gac_restriction (entity_type, entity_id, scope_path, type, rule, config, is_disabled) VALUES
        ('1', 10, '/sucursal/caracas', 'ip', 'allow', '{\"list\":[\"192.168.10.1\"]}', '0')");

    $gac = new \DancasDev\GAC\GAC($pdo);
    $gac->setEntity('user', 10)->setScope('/sucursal/caracas');

    // Desde IP autorizada -> ALLOW
    assert($gac->can('caja_chica', 'read', ['ip' => ['ip' => '192.168.10.1']]) === true);

    // Desde IP no autorizada -> DENY por restriccion
    assert($gac->can('caja_chica', 'read', ['ip' => ['ip' => '10.0.0.1']]) === false);

    // En /sucursal/maracaibo (no tiene restriccion de entidad) -> fallback a global
    $gac->setScope('/sucursal/maracaibo');
    assert($gac->can('caja_chica', 'read', ['ip' => ['ip' => '192.168.1.50']]) === true);
});

test('Restriccion heredada de rol con scope NULL hereda scope_path de gac_role_entity', function () use ($pdo) {
    // Rol 20 con restriccion date y scope NULL
    $pdo->exec("INSERT INTO gac_role (id, is_disabled) VALUES (20, '0')");
    $pdo->exec("INSERT INTO gac_permission (module_id, entity_type, entity_id, scope_path, feature, level, is_disabled) VALUES
        (10, '0', 20, NULL, 7, '1', '0')");
    $pdo->exec("INSERT INTO gac_restriction (entity_type, entity_id, scope_path, type, rule, config, is_disabled) VALUES
        ('0', 20, NULL, 'ip', 'deny', '{\"list\":[\"172.16.0.1\"]}', '0')");

    // Usuario 20 vinculado a Rol 20 con scope_path = "/zona/norte,/zona/sur"
    $pdo->exec("INSERT INTO gac_role_entity (role_id, entity_type, entity_id, scope_path, priority, is_disabled) VALUES
        (20, '1', 20, '/zona/norte,/zona/sur', 0, '0')");

    $gac = new \DancasDev\GAC\GAC($pdo);
    $gac->setEntity('user', 20)->setScope('/zona/norte');

    // En /zona/norte con IP denegada -> DENY
    assert($gac->can('caja_chica', 'read', ['ip' => ['ip' => '172.16.0.1']]) === false);

    // En /zona/norte con IP permitida -> ALLOW
    assert($gac->can('caja_chica', 'read', ['ip' => ['ip' => '192.168.1.100']]) === true);
});
