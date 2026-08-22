<?php
echo "\n=== Utils ===\n";

use DancasDev\GAC\Utils;

// ── Scope Validate Tests ──────────────────────────────────────────────────
test('scopeValidate(null) = true (herencia dinámica)', function () {
    assert(Utils::scopeValidate(null) === true);
});

test('scopeValidate("*") = true (wildcard global)', function () {
    assert(Utils::scopeValidate('*') === true);
});

test('scopeValidate("/sucursal/1") = true', function () {
    assert(Utils::scopeValidate('/sucursal/1') === true);
});

test('scopeValidate("/sucursal/1,/sucursal/2") = true (multi-path)', function () {
    assert(Utils::scopeValidate('/sucursal/1,/sucursal/2') === true);
});

test('scopeValidate("/sucursal/*") = true (wildcard prefijo)', function () {
    assert(Utils::scopeValidate('/sucursal/*') === true);
});

test('scopeValidate("/api/v1/documents/*") = true', function () {
    assert(Utils::scopeValidate('/api/v1/documents/*') === true);
});

test('scopeValidate("/sucursal/1,/sucursal/2,/global/*") = true', function () {
    assert(Utils::scopeValidate('/sucursal/1,/sucursal/2,/global/*') === true);
});

test('scopeValidate("") = false (string vacio)', function () {
    assert(Utils::scopeValidate('') === false);
});

test('scopeValidate("/*") = false (wildcard sin prefijo; usar *)', function () {
    assert(Utils::scopeValidate('/*') === false);
});

test('scopeValidate("//sucursal") = false (doble slash)', function () {
    assert(Utils::scopeValidate('//sucursal') === false);
});

test('scopeValidate("/sucursal/") = false (trailing slash)', function () {
    assert(Utils::scopeValidate('/sucursal/') === false);
});

test('scopeValidate("/sucursal/*/depto") = false (wildcard intermedio)', function () {
    assert(Utils::scopeValidate('/sucursal/*/depto') === false);
});

test('scopeValidate("sucursal/1") = true (compatible con v1)', function () {
    assert(Utils::scopeValidate('sucursal/1') === true);
    assert(Utils::scopeValidate('empresaX/*') === true);
});

test('scopeValidate("/sucursal/1,/sucursal/1") = false (duplicado)', function () {
    assert(Utils::scopeValidate('/sucursal/1,/sucursal/1') === false);
});

test('scopeValidate("/sucursal/@2") = false (caracter especial)', function () {
    assert(Utils::scopeValidate('/sucursal/@2') === false);
    assert(Utils::scopeValidate('/sucursal/#1') === false);
    assert(Utils::scopeValidate('/sucursal/?query=1') === false);
});

test('scopeValidate("**") = false (doble wildcard)', function () {
    assert(Utils::scopeValidate('**') === false);
});

test('scopeValidate("/a b/c") = false (espacio en segmento)', function () {
    assert(Utils::scopeValidate('/a b/c') === false);
});

test('scopeValidate con espacios alrededor de coma se limpian', function () {
    assert(Utils::scopeValidate('/sucursal/1 , /sucursal/2') === true);
    assert(Utils::scopeValidate(' /sucursal/1 ') === true);
});

test('scopeValidate con comas invalidas', function () {
    assert(Utils::scopeValidate(',') === false);
    assert(Utils::scopeValidate(' , ') === false);
    assert(Utils::scopeValidate('/sucursal/1,') === false);
    assert(Utils::scopeValidate(',/sucursal/1') === false);
    assert(Utils::scopeValidate('/sucursal/1,,/sucursal/2') === false);
});

// ── Scope Has Collision Tests ─────────────────────────────────────────────
test('scopeHasCollision con arreglo asociativo de BD (fetchAll) detecta colision', function () {
    $dbRecords = [
        ['id' => 1, 'scope_path' => '/sucursal/caracas,/sucursal/maracaibo'],
        ['id' => 2, 'scope_path' => '/sucursal/valencia'],
    ];

    assert(Utils::scopeHasCollision($dbRecords, '/sucursal/caracas') === true);
    assert(Utils::scopeHasCollision($dbRecords, '/sucursal/maracaibo') === true);
    assert(Utils::scopeHasCollision($dbRecords, '/sucursal/valencia') === true);
    assert(Utils::scopeHasCollision($dbRecords, '/sucursal/barquisimeto') === false);
});

test('scopeHasCollision con multi-path en nuevo scope detecta colision parcial', function () {
    $dbRecords = [
        ['scope_path' => '/sucursal/caracas,/sucursal/maracaibo'],
    ];

    assert(Utils::scopeHasCollision($dbRecords, '/sucursal/barquisimeto,/sucursal/caracas') === true);
    assert(Utils::scopeHasCollision($dbRecords, '/sucursal/barquisimeto,/sucursal/puerto_ordaz') === false);
});

test('scopeHasCollision con arreglo de strings planos', function () {
    $existing = ['/zona/norte,/zona/sur', '/zona/este'];

    assert(Utils::scopeHasCollision($existing, '/zona/sur') === true);
    assert(Utils::scopeHasCollision($existing, '/zona/oeste') === false);
});

test('scopeHasCollision con string unico o nulos', function () {
    assert(Utils::scopeHasCollision('/zona/norte', '/zona/norte') === true);
    assert(Utils::scopeHasCollision('/zona/norte', '/zona/sur') === false);
    assert(Utils::scopeHasCollision(null, '/zona/norte') === false);
    assert(Utils::scopeHasCollision([], '/zona/norte') === false);
    assert(Utils::scopeHasCollision('/zona/norte', null) === false);
});
