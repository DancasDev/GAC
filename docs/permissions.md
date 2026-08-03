# Permisos

GAC maneja permisos con **bitmask** por módulo, heredados por **categoría** y
resueltos según **alcance jerárquico** (scope) y **prioridad** de entidad.

---

## 1. Las tablas

Cinco tablas trabajan juntas. Créelas con:

```php
use DancasDev\GAC\Schema;
Schema::install($pdo);
```

### `gac_module_category` — Categorías de módulos

Agrupa módulos. Ej: `sistema`, `reportes`, `directorios`.

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT AUTO_INCREMENT | Identificador |
| `code` | VARCHAR(40) | Código único (`sistema`) |
| `is_disabled` | ENUM('0','1') | `'0'` = activo |

### `gac_module` — Módulos del sistema

Cada módulo pertenece a una categoría.

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT AUTO_INCREMENT | Identificador |
| `module_category_id` | INT | FK a `gac_module_category` |
| `code` | VARCHAR(40) | Código único (`users`) |
| `is_developing` | ENUM('0','1') | `'1'` = en desarrollo |
| `is_disabled` | ENUM('0','1') | `'0'` = activo |

### `gac_role` — Roles

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT AUTO_INCREMENT | Identificador |
| `is_disabled` | ENUM('0','1') | `'0'` = activo |

### `gac_role_entity` — Quién tiene qué rol

Vincula usuarios o clientes a roles, con prioridad.

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `role_id` | INT | FK a `gac_role` |
| `entity_type` | ENUM('1','2') | `'1'` = usuario, `'2'` = cliente |
| `entity_id` | INT | ID del usuario o cliente |
| `priority` | TINYINT | `0` = rol principal, `>0` = secundario |

> ⚠️ Un usuario solo puede tener **un rol por cada valor de `priority`**.

### `gac_permission` — La tabla central

**Aquí se define quién puede hacer qué, sobre qué módulo y en qué alcance.**

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `module_id` | INT | FK a `gac_module(id)` — módulo al que se otorga el permiso |
| `entity_type` | ENUM('0','1','2') | `'0'`=rol, `'1'`=usuario, `'2'`=cliente |
| `entity_id` | INT | ID del rol, usuario o cliente |
| `scope_path` | VARCHAR(255) | Alcance: `*`, `empresaX`, `empresaX/*` |
| `feature` | SMALLINT | Bitmask de accesos |
| `level` | ENUM('0','1','2') | `'0'`=bajo, `'1'`=normal, `'2'`=alto |
| `payload` | LONGTEXT | Datos extra en JSON (p. ej. `{"locker_ids":[1,2]}`), NULL si no tiene |
| `is_disabled` | ENUM('0','1') | `'0'` = activo |

---

## 2. Bitmask de `feature`

Cada permiso individual es un bit. Súmelos para combinarlos.

| Permiso | Bit | Valor |
|---------|-----|-------|
| Crear | 0 | 1 |
| Leer | 1 | 2 |
| Actualizar | 2 | 4 |
| Eliminar | 3 | 8 |
| Papelera | 4 | 16 |
| Modo desarrollo | 5 | 32 |

**Ejemplos:**

| Combinación | Suma | `feature` |
|-------------|------|-----------|
| Solo leer | 2 | 2 |
| Crear + Leer | 1 + 2 | 3 |
| Crear + Leer + Actualizar | 1 + 2 + 4 | 7 |
| Todo menos desarrollo | 1 + 2 + 4 + 8 + 16 | 31 |
| Todo | 1 + 2 + 4 + 8 + 16 + 32 | 63 |

---

## 3. Empiece por aquí

### 3.1 Cree categorías y módulos

```sql
INSERT INTO gac_module_category (id, code) VALUES
    (1, 'sistema'),
    (2, 'perfil');

INSERT INTO gac_module (id, module_category_id, code, is_developing) VALUES
    (1, 1, 'users', '0'),
    (2, 1, 'roles', '0'),
    (3, 2, 'my_profile', '0');
```

### 3.2 Cree roles y asigne usuarios

```sql
INSERT INTO gac_role (id) VALUES (1), (2);

INSERT INTO gac_role_entity (role_id, entity_type, entity_id, priority) VALUES
    (1, '1', 10, 0),  -- usuario 10 es admin (rol principal)
    (2, '1', 20, 0);  -- usuario 20 es viewer (rol principal)
```

### 3.3 Asigne permisos al rol `admin`

```sql
-- El rol admin tiene control total (63) sobre los módulos de "sistema"
-- en cualquier sucursal
INSERT INTO gac_permission
    (module_id, entity_type, entity_id, scope_path, feature, level)
VALUES (1, '0', 1, '*', 63, '1'),  -- users
       (2, '0', 1, '*', 63, '1');  -- roles
```

> Los permisos apuntan **directamente a un módulo** (`module_id`). No hay
> permisos por categoría: si agrega un módulo nuevo, debe otorgarle permiso
> explícitamente.

```sql
-- El rol viewer solo puede leer (2) el módulo "users"
INSERT INTO gac_permission
    (module_id, entity_type, entity_id, scope_path, feature, level)
VALUES (1, '0', 2, '*', 2, '1');
```

### 3.4 Asigne permisos directos (a un usuario sobre un módulo)

```sql
-- El usuario 30 (que por rol es viewer) puede editar users
-- pero solo en la sucursal "Tigre"
INSERT INTO gac_permission
    (module_id, entity_type, entity_id, scope_path, feature, level)
VALUES (1, '1', 30, 'Tigre/*', 7, '1');
```

| Campo | ¿Por qué este valor? |
|-------|---------------------|
| `module_id=1` | Módulo `users` (FK a `gac_module`) |
| `entity_type='1'` | Es un usuario, no un rol |
| `scope_path='Tigre/*'` | Aplica a Tigre y sus sub-sucursales |
| `feature=7` | Crear + Leer + Actualizar |

---

## 4. Scope: cómo funciona el alcance

Cuando verifica permisos con un scope específico (ej: `'empresaX/SucursalA'`),
GAC resuelve cuál registro de `gac_permission` aplica usando estas reglas,
**en orden**:

| Prioridad | Regla | Ejemplo con scope `'empresaX/SucursalA'` |
|-----------|-------|----------------------------------------|
| 1 (gana) | **Match exacto** | `scope_path = 'empresaX/SucursalA'` |
| 2 | **Wildcard más profundo** | `scope_path = 'empresaX/SucursalA/*'` gana sobre `'empresaX/*'` |
| 3 | **Fallback global** | `scope_path = '*'` |

### Casos de ejemplo

Scope actual: `'empresaX/SucursalA/Deposito'`

| Registros en `gac_permission` | ¿Cuál aplica? | ¿Por qué? |
|--------------------------------------|---------------|-----------|
| `*`, `empresaX/*` | `empresaX/*` | Wildcard cubre |
| `*`, `empresaX/SucursalA/*`, `empresaX/*` | `empresaX/SucursalA/*` | Es el wildcard más profundo |
| `*`, `otraEmpresa/*` | `*` | Solo el global matchea |
| `empresaX` (sin `/*`) | **Ninguno** | Los scopes sin `/*` no heredan hacia abajo |

> ⚠️ `'empresaX'` solo aplica al scope **exacto** `'empresaX'`. Para que cubra
> sub-sucursales, use `'empresaX/*'`.

---

## 5. Prioridad

Cuando un mismo módulo tiene permisos de distintas fuentes, GAC elige por prioridad:

| Fuente | Prioridad | Cuándo gana |
|--------|-----------|-------------|
| Permiso personal (`entity_type='1'` o `'2'`) | `-1` (máxima) | Siempre que exista |
| Permiso de rol (`entity_type='0'`) | `priority` de `gac_role_entity` | Si no hay permiso personal |

La deduplicación es por **combinación `(módulo, scope_path)`**. Esto significa que
un usuario puede tener un permiso personal con scope `'Tigre'` y heredar el permiso
de su rol con scope `'*'` — ambos conviven porque tienen distinto scope.

---

## 6. Verifique permisos desde PHP

```php
$gac = new GAC($pdo);
$gac->setEntity('user', 30)->setScope('Tigre/SucursalB');

$p = $gac->getPermissions();

// ¿El usuario tiene permiso para el módulo "users"?
if ($p->has('users')) {
    $permiso = $p->get('users');

    echo $permiso->getFeature();          // int: bitmask (ej: 7)
    echo $permiso->getLevel();            // int: 0, 1 o 2

    // Preguntar por features específicos
    $permiso->hasFeature('create');       // bool
    $permiso->hasFeature('read');         // bool
    $permiso->hasFeature(['create', 'read']); // bool: true si tiene AMBOS

    // ¿El módulo está en modo desarrollo?
    $permiso->moduleIsDeveloping();       // bool
}
```

### Datos extra del permiso (`payload`)

El campo `payload` guarda datos JSON arbitrarios que el controlador usa para
**restringir aún más** el acceso. Ejemplo: un módulo sube archivos a "lockers" y
ciertos usuarios solo pueden subir a determinados lockers.

```sql
-- El usuario 30 puede subir archivos, pero SOLO a los lockers 1 y 2
INSERT INTO gac_permission
    (module_id, entity_type, entity_id, scope_path, feature, level, payload)
VALUES (5, '1', 30, '*', 1, '1', '{"locker_ids":[1,2]}');
```

```php
$permiso = $gac->getPermissions()->get('files');

if ($permiso && $permiso->hasFeature('create')) {
    $lockers = $permiso->getPayload()['locker_ids'] ?? [];  // [1, 2]

    // En el controlador: valida que el locker destino esté permitido
    if (!in_array($lockerId, $lockers)) {
        throw new \Exception('No tienes acceso a ese locker');
    }
}
```

> `getPayload()` devuelve el array decodificado, o `NULL` si el permiso no tiene
> payload. El JSON es libre: la estructura depende de cada módulo.

### Listar todos los permisos (sin resolver scope)

```php
$lista = $gac->getPermissionList();
// ['users' => [['s' => '*', 'f' => 2, ...], ['s' => 'Tigre/*', 'f' => 7, ...]], ...]
```

### Listar permisos filtrados por scope

```php
$lista = $gac->getPermissionList('Tigre/*');
// ['users' => ['f' => 7, 'i' => 123, 'd' => '0', 'l' => 1], ...]
```

---

## 7. Escenarios completos

### "Solo los administradores pueden ver usuarios"

```sql
-- Paso 1: categoría y módulo
INSERT INTO gac_module_category (id, code) VALUES (1, 'sistema');
INSERT INTO gac_module (id, module_category_id, code) VALUES (1, 1, 'users');

-- Paso 2: rol
INSERT INTO gac_role (id) VALUES (1);

-- Paso 3: permiso (feature=63 = todo)
INSERT INTO gac_permission
    (module_id, entity_type, entity_id, scope_path, feature, level)
VALUES (1, '0', 1, '*', 63, '1');

-- Paso 4: asignar usuario 10 al rol admin
INSERT INTO gac_role_entity (role_id, entity_type, entity_id, priority)
VALUES (1, '1', 10, 0);
```

### "Un usuario puede editar solo en su sucursal"

```sql
-- El usuario 30 ya pertenece al rol "viewer" (solo lectura en *)
-- Le damos permiso extra personal sobre users en "Tigre"

INSERT INTO gac_permission
    (module_id, entity_type, entity_id, scope_path, feature, level)
VALUES (1, '1', 30, 'Tigre/*', 7, '1');
-- feature=7 = crear(1) + leer(2) + actualizar(4)
```

### "Quiero que el permiso cubra a un cliente (no usuario)"

```sql
-- entity_type en gac_role_entity y gac_permission usa '2' para cliente
INSERT INTO gac_role_entity (role_id, entity_type, entity_id, priority)
VALUES (1, '2', 5, 0);  -- cliente 5 es admin
```

```php
// En PHP, usar 'client' como entity type
$gac->setEntity('client', 5)->setScope('*');
```

---

## 8. Caché y purga

GAC cachea los permisos para no consultar la base de datos en cada request.

```php
// Constructor con caché a archivo
$gac = new GAC($pdo, ['dir' => __DIR__ . '/cache', 'ttl' => 3600]);

// Limpiar caché de la entidad actual
$gac->clearCache();

// Limpiar también el caché global (restricciones globales)
$gac->clearCache(true);

// Cuando modifique permisos de un usuario, purgue su caché
$gac->purgeCacheBy('user', [30]);

// Si modificaste un rol
$gac->purgeCacheBy('role', [1]);

// Si modificaste permisos de un cliente
$gac->purgeCacheBy('client', [5]);

// Si modificaste restricciones globales
$gac->purgeCacheBy('global');
```

---

## Migrar desde la versión con categorías

Los permisos ya no apuntan a categorías: ahora `module_id` referencia un módulo
directamente, y `from_entity_type`/`from_entity_id` pasan a llamarse
`entity_type`/`entity_id`. Para migrar una instalación existente:

```sql
-- 1. Nuevas columnas (MySQL)
ALTER TABLE gac_permission
    ADD COLUMN module_id INT NOT NULL AFTER id,
    ADD COLUMN payload LONGTEXT NULL AFTER level;

-- 2. Backfill: los permisos directos a módulo copian su destino
UPDATE gac_permission
SET module_id = to_entity_id
WHERE to_entity_type = '1';

-- 3. Los permisos por categoría se expanden a cada módulo de esa categoría
INSERT INTO gac_permission (module_id, from_entity_type, from_entity_id, scope_path, feature, level, is_disabled, deleted_at)
SELECT m.id, p.from_entity_type, p.from_entity_id, p.scope_path, p.feature, p.level, p.is_disabled, p.deleted_at
FROM gac_permission p
JOIN gac_module m ON m.module_category_id = p.to_entity_id
WHERE p.to_entity_type = '0' AND p.deleted_at IS NULL;

-- 4. Eliminar columnas viejas y renombrar (después de validar)
ALTER TABLE gac_permission
    DROP COLUMN to_entity_type,
    DROP COLUMN to_entity_id,
    CHANGE COLUMN from_entity_type entity_type ENUM('0','1','2') NOT NULL,
    CHANGE COLUMN from_entity_id entity_id INT NOT NULL;

-- 5. Índice único + FK (MySQL)
ALTER TABLE gac_permission
    ADD UNIQUE KEY uk_perm (entity_type, entity_id, module_id, scope_path),
    ADD KEY idx_perm_module (module_id),
    ADD CONSTRAINT fk_gac_permission_module FOREIGN KEY (module_id) REFERENCES gac_module(id);
```

> ⚠️ Los grants por categoría no son 1:1: cada fila de categoría se convierte en
> una fila por módulo. Verifique los `feature` resultantes antes de eliminar las
> columnas viejas.

---

## Errores comunes

| Error | Causa | Solución |
|-------|-------|----------|
| `$p->get('users')` devuelve `null` | El usuario/rol no tiene permiso para ese módulo | Verifique los INSERTs en `gac_permission` |
| `hasFeature('read')` devuelve `false` cuando `feature=3` | `feature=3` = crear+leer. El bit de lectura (1) sí está | Revise que no esté llamando `hasFeature` con mayúsculas. Use **minúsculas** |
| Un módulo nuevo no aparece para el admin | Los permisos ahora son por módulo, no por categoría | Otorgue el permiso explícitamente al rol (`module_id` del módulo nuevo) |
| Se insertó un permiso nuevo pero el usuario sigue sin tenerlo | El caché aún no expiró | Llame a `purgeCacheBy('user', [id])` o `purgeCacheBy('role', [id])` para forzar la recarga. Mientras no purgue, el usuario verá los permisos anteriores |
| Scope `'empresaX'` no cubre `'empresaX/Sucursal'` | Sin `/*` no hereda | Use `'empresaX/*'` |
| Dos permisos compiten y gana el que no esperaba | La prioridad personal (`-1`) siempre gana sobre el rol | Si desea que el rol defina el permiso, no cree un permiso personal que solape |
