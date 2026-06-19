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
| `code` | VARCHAR(30) | Código único (`admin`) |
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

### `gac_module_permission` — La tabla central

**Aquí se define quién puede hacer qué, sobre qué módulo y en qué alcance.**

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `from_entity_type` | ENUM('0','1','2') | `'0'`=rol, `'1'`=usuario, `'2'`=cliente |
| `from_entity_id` | INT | ID del rol, usuario o cliente |
| `to_entity_type` | ENUM('0','1') | `'0'`=categoría, `'1'`=módulo directo |
| `to_entity_id` | INT | ID de la categoría o módulo destino |
| `scope_path` | VARCHAR(255) | Alcance: `*`, `empresaX`, `empresaX/*` |
| `feature` | SMALLINT | Bitmask de accesos |
| `level` | ENUM('0','1','2') | `'0'`=bajo, `'1'`=normal, `'2'`=alto |
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
INSERT INTO gac_role (id, code) VALUES (1, 'admin'), (2, 'viewer');

INSERT INTO gac_role_entity (role_id, entity_type, entity_id, priority) VALUES
    (1, '1', 10, 0),  -- usuario 10 es admin (rol principal)
    (2, '1', 20, 0);  -- usuario 20 es viewer (rol principal)
```

### 3.3 Asigne permisos por categoría (a todo un rol)

```sql
-- El rol admin tiene control total (63) sobre todos los módulos
-- de la categoría "sistema" en cualquier sucursal
INSERT INTO gac_module_permission
    (from_entity_type, from_entity_id, to_entity_type, to_entity_id, scope_path, feature, level)
VALUES ('0', 1, '0', 1, '*', 63, '1');
```

> Al usar `to_entity_type='0'` (categoría), el permiso se **expande** a todos los
> módulos de esa categoría. Si crea un módulo nuevo en `sistema`, el admin lo
> hereda automáticamente.

```sql
-- El rol viewer solo puede leer (2) los módulos de "sistema"
INSERT INTO gac_module_permission
    (from_entity_type, from_entity_id, to_entity_type, to_entity_id, scope_path, feature, level)
VALUES ('0', 2, '0', 1, '*', 2, '1');
```

### 3.4 Asigne permisos directos (a un usuario sobre un módulo)

```sql
-- El usuario 30 (que por rol es viewer) puede editar users
-- pero solo en la sucursal "Tigre"
INSERT INTO gac_module_permission
    (from_entity_type, from_entity_id, to_entity_type, to_entity_id, scope_path, feature, level)
VALUES ('1', 30, '1', 1, 'Tigre/*', 7, '1');
```

| Campo | ¿Por qué este valor? |
|-------|---------------------|
| `from_entity_type='1'` | Es un usuario, no un rol |
| `to_entity_type='1'` | Permiso directo al módulo, no por categoría |
| `scope_path='Tigre/*'` | Aplica a Tigre y sus sub-sucursales |
| `feature=7` | Crear + Leer + Actualizar |

---

## 4. Scope: cómo funciona el alcance

Cuando verifica permisos con un scope específico (ej: `'empresaX/SucursalA'`),
GAC resuelve cuál registro de `gac_module_permission` aplica usando estas reglas,
**en orden**:

| Prioridad | Regla | Ejemplo con scope `'empresaX/SucursalA'` |
|-----------|-------|----------------------------------------|
| 1 (gana) | **Match exacto** | `scope_path = 'empresaX/SucursalA'` |
| 2 | **Wildcard más profundo** | `scope_path = 'empresaX/SucursalA/*'` gana sobre `'empresaX/*'` |
| 3 | **Fallback global** | `scope_path = '*'` |

### Casos de ejemplo

Scope actual: `'empresaX/SucursalA/Deposito'`

| Registros en `gac_module_permission` | ¿Cuál aplica? | ¿Por qué? |
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
| Permiso personal (`from_entity_type='1'` o `'2'`) | `-1` (máxima) | Siempre que exista |
| Permiso de rol (`from_entity_type='0'`) | `priority` de `gac_role_entity` | Si no hay permiso personal |

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
INSERT INTO gac_role (id, code) VALUES (1, 'admin');

-- Paso 3: permiso (feature=63 = todo)
INSERT INTO gac_module_permission
    (from_entity_type, from_entity_id, to_entity_type, to_entity_id, scope_path, feature, level)
VALUES ('0', 1, '0', 1, '*', 63, '1');

-- Paso 4: asignar usuario 10 al rol admin
INSERT INTO gac_role_entity (role_id, entity_type, entity_id, priority)
VALUES (1, '1', 10, 0);
```

### "Un usuario puede editar solo en su sucursal"

```sql
-- El usuario 30 ya pertenece al rol "viewer" (solo lectura en *)
-- Le damos permiso extra personal sobre users en "Tigre"

INSERT INTO gac_module_permission
    (from_entity_type, from_entity_id, to_entity_type, to_entity_id, scope_path, feature, level)
VALUES ('1', 30, '1', 1, 'Tigre/*', 7, '1');
-- feature=7 = crear(1) + leer(2) + actualizar(4)
```

### "Quiero que el permiso cubra a un cliente (no usuario)"

```sql
-- entity_type en gac_role_entity y gac_module_permission usa '2' para cliente
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
$gac = new GAC($pdo, ['driver' => 'file', 'path' => __DIR__ . '/cache', 'ttl' => 3600]);

// Limpiar caché manual
$gac->clearCache();

// Cuando modifique permisos de un usuario, purgue su caché
$gac->purgePermissionsBy('user', [30]);

// Si modificaste un rol
$gac->purgePermissionsBy('role', [1]);
```

---

## Errores comunes

| Error | Causa | Solución |
|-------|-------|----------|
| `$p->get('users')` devuelve `null` | El usuario/rol no tiene permiso para ese módulo | Verifique los INSERTs en `gac_module_permission` |
| `hasFeature('read')` devuelve `false` cuando `feature=3` | `feature=3` = crear+leer. El bit de lectura (1) sí está | Revise que no esté llamando `hasFeature` con mayúsculas. Use **minúsculas** |
| Un módulo nuevo no aparece para el admin | Fue creado después de que el admin heredó por categoría | El admin hereda **automáticamente** si el módulo está en la categoría correcta. Si no aparece, revise `module_category_id` |
| Se insertó un permiso nuevo pero el usuario sigue sin tenerlo | El caché aún no expiró | Llame a `purgePermissionsBy('user', [id])` o `purgePermissionsBy('role', [id])` para forzar la recarga. Mientras no purgue, el usuario verá los permisos anteriores |
| Scope `'empresaX'` no cubre `'empresaX/Sucursal'` | Sin `/*` no hereda | Use `'empresaX/*'` |
| Dos permisos compiten y gana el que no esperaba | La prioridad personal (`-1`) siempre gana sobre el rol | Si desea que el rol defina el permiso, no cree un permiso personal que solape |
