<?php
echo "\n=== Stress Testing & Performance Benchmark ===\n";

use DancasDev\GAC\GAC;
use DancasDev\GAC\Drivers\Cache\FileCache;

TestCase::resetData();
$pdo = TestCase::$pdo;

// Setup benchmark entities with multi-path, inheritance, and wildcards
$pdo->exec("INSERT INTO gac_module (id, module_category_id, code, is_developing, is_disabled) VALUES
    (10, 1, 'caja_chica', '0', '0'),
    (11, 1, 'documentos', '0', '0')");

$pdo->exec("INSERT INTO gac_role (id, is_disabled) VALUES (10, '0'), (11, '0')");

// Permisos
$pdo->exec("INSERT INTO gac_permission (module_id, entity_type, entity_id, scope_path, feature, level, is_disabled) VALUES
    (10, '0', 10, NULL, 7, '1', '0'),
    (11, '0', 11, '/docs/legal/*,/docs/publico', 3, '1', '0')");

// Rol entities
$pdo->exec("INSERT INTO gac_role_entity (role_id, entity_type, entity_id, scope_path, priority, is_disabled) VALUES
    (10, '1', 10, '/sucursal/caracas,/sucursal/maracaibo', 0, '0'),
    (11, '1', 12, NULL, 0, '0')");

$tmpDir = sys_get_temp_dir() . '/gac_stress_' . uniqid();
$cache = new FileCache($tmpDir);

// ── Test 1: Benchmark Cold Cache vs Hot Cache ───────────────────────────────
test('Benchmark: Cold Cache vs Hot Cache evaluation time', function () use ($pdo, $cache) {
    $gac = new GAC($pdo, $cache);
    $gac->setEntity('user', 1)->setScope('empresaX/SucursalA');
    $gac->clearCache();

    // 1. Cold Cache: Primera llamada (ejecuta queries DB, procesa granularidad, escribe cache)
    $t0 = microtime(true);
    $coldResult = $gac->can('users', 'read');
    $coldDuration = (microtime(true) - $t0) * 1000; // ms

    assert($coldResult === true);

    // 2. Hot Cache: 100 llamadas subsiguientes
    $t1 = microtime(true);
    for ($i = 0; $i < 100; $i++) {
        $hotResult = $gac->can('users', 'read');
        assert($hotResult === true);
    }
    $hotDuration = ((microtime(true) - $t1) * 1000) / 100; // promedio ms por call

    echo sprintf("    [Perf] Cold Cache: %.3f ms | Hot Cache (avg): %.4f ms (Speedup: ~%.1fx)\n",
        $coldDuration,
        $hotDuration,
        $coldDuration / max($hotDuration, 0.0001)
    );

    assert($hotDuration < $coldDuration, 'Hot Cache debe ser significativamente más rápido que Cold Cache');
});

// ── Test 2: 10,000+ Evaluaciones Consecutivas & Memory Leak Check ───────────
test('Stress: 10,000+ evaluaciones can() e isRestricted() sin memory leaks', function () use ($pdo, $cache) {
    $gac = new GAC($pdo, $cache);

    $entities = [
        ['type' => 'user', 'id' => 1, 'scope' => 'empresaX/SucursalA', 'module' => 'users', 'feature' => 'read', 'expectedCan' => true],
        ['type' => 'user', 'id' => 1, 'scope' => 'empresaX', 'module' => 'users', 'feature' => 'read', 'expectedCan' => false],
        ['type' => 'user', 'id' => 1, 'scope' => '*', 'module' => 'users', 'feature' => 'delete', 'expectedCan' => true],
        ['type' => 'user', 'id' => 10, 'scope' => '/sucursal/caracas', 'module' => 'caja_chica', 'feature' => 'update', 'expectedCan' => true],
        ['type' => 'user', 'id' => 10, 'scope' => '/sucursal/3', 'module' => 'caja_chica', 'feature' => 'read', 'expectedCan' => false],
        ['type' => 'user', 'id' => 12, 'scope' => '/docs/legal/2026', 'module' => 'documentos', 'feature' => 'read', 'expectedCan' => true],
        ['type' => 'user', 'id' => 12, 'scope' => '/docs/privado', 'module' => 'documentos', 'feature' => 'read', 'expectedCan' => false],
    ];

    $contextAllowed = [
        'ip' => ['ip' => '192.168.1.50'],
        'domain' => ['host' => 'localhost'],
    ];

    $contextDenied = [
        'ip' => ['ip' => '200.0.0.1'],
    ];

    // Calentar cache para todas las entidades
    foreach ($entities as $e) {
        $gac->setEntity($e['type'], $e['id'])->setScope($e['scope']);
        $gac->getPermissions();
        $gac->getRestrictions();
    }

    gc_collect_cycles();
    $memStart = memory_get_usage();
    $t0 = microtime(true);

    $iterations = 10000;
    $numEntities = count($entities);

    for ($i = 0; $i < $iterations; $i++) {
        $e = $entities[$i % $numEntities];
        $gac->setEntity($e['type'], $e['id'])->setScope($e['scope']);

        // Evaluación de permiso
        $can = $gac->can($e['module'], $e['feature']);
        assert($can === $e['expectedCan'], "Fallo en iteracion $i para entidad {$e['id']}");

        // Evaluación con contexto de restriccion permitido
        $canWithCtx = $gac->can($e['module'], $e['feature'], null, $contextAllowed);
        assert($canWithCtx === $e['expectedCan'], "Fallo en contexto permitido para iteracion $i");

        // Evaluación de restricción
        $isRestricted = $gac->isRestricted($contextDenied);
        assert($isRestricted === true, "Fallo en isRestricted para iteracion $i");
    }

    $totalTime = (microtime(true) - $t0) * 1000;
    $opsPerSec = ($iterations * 3) / max(($totalTime / 1000), 0.0001); // 3 operaciones por iteración = 30,000 ops

    gc_collect_cycles();
    $memEnd = memory_get_usage();
    $memDiff = ($memEnd - $memStart) / 1024; // KB

    echo sprintf("    [Stress] %d iteraciones (30,000 ops) en %.2f ms | Throughput: %.0f ops/sec | Delta RAM: %.2f KB\n",
        $iterations,
        $totalTime,
        $opsPerSec,
        $memDiff
    );

    // Delta de memoria no debe crecer significativamente (menos de 100 KB de fragmentación normal)
    assert($memDiff < 100, "Posible fuga de memoria detectada: Delta de RAM fue de {$memDiff} KB");
});

// Cleanup
$cache->clean();
rmdir($tmpDir);
