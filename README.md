# GAC — Granular Access Control

Librería PHP para gestionar **permisos** y **restricciones** de acceso con granularidad
por módulo, rol, usuario y alcance jerárquico. Compatible con MySQL y PostgreSQL.

## Requisitos

- PHP 8.0 o superior
- MySQL 5.7+ o PostgreSQL 12+
- Extensión PDO (o `ext-mysqli` / `ext-pgsql` con los adapters nativos)

## Instalación

```bash
composer require dancasdev/gac
```

## Escenario real en 2 minutos

Suponga que tiene un sistema con un módulo `users` y desea que:

- El rol `admin` tenga acceso **total** a `users` en **todas** las sucursales
- El usuario con ID 5 pueda **crear y leer** usuarios, pero **solo en la sucursal `SucursalNorte`**
- Nadie pueda acceder al sistema **fuera del horario de 8:00 a 18:00**

### Paso 1 — Crear las tablas

```php
use DancasDev\GAC\Schema;

Schema::install($pdo);   // Crea todas las tablas necesarias
// Schema::uninstall($pdo); // Elimina solo las tablas GAC
```

### Paso 2 — Registrar módulos y roles

```sql
INSERT INTO gac_module_category (id, code) VALUES (1, 'sistema');

INSERT INTO gac_module (id, module_category_id, code) VALUES (1, 1, 'users');

INSERT INTO gac_role (id) VALUES (1), (2);
```

### Paso 3 — Asignar permisos al rol `admin`

```sql
-- admin tiene acceso total (63) al módulo "users" en todas las sucursales
INSERT INTO gac_permission
    (module_id, entity_type, entity_id, scope_path, feature, level)
VALUES (1, '0', 1, '*', 63, '1');
```

| Tipo | Valor | Significado |
|------|-------|-------------|
| `module_id` | `1` | FK al módulo `users` |
| `entity_type` | `'0'` | El permiso viene de un **rol** |
| `entity_id` | `1` | ID del rol `admin` |
| `scope_path` | `'*'` | En todas las sucursales |
| `feature` | `63` | Bitmask completo: `1+2+4+8+16+32` |
| `level` | `'1'` | Nivel normal |

### Paso 4 — Permiso extra para un usuario específico

```sql
-- El usuario 5 puede crear y leer (1+2=3) users, solo en SucursalNorte
INSERT INTO gac_permission
    (module_id, entity_type, entity_id, scope_path, feature, level)
VALUES (1, '1', 5, 'SucursalNorte', 3, '1');
```

| Tipo | Valor | Significado |
|------|-------|-------------|
| `module_id` | `1` | FK al módulo `users` |
| `entity_type` | `'1'` | El permiso viene de un **usuario** |
| `feature` | `3` | Crear + Leer |

### Paso 5 — Restricción horaria global

```sql
-- Nadie accede fuera de 08:00 a 18:00
INSERT INTO gac_restriction (entity_type, entity_id, scope_path, type, rule, config)
VALUES ('3', 0, '*', 'date', 'in_range', '{"sd":"%Y-%M-%D 08:00","ed":"%Y-%M-%D 18:00"}');
```

### Paso 6 — Verificar acceso en PHP

```php
$gac = new GAC($pdo);

// Verificar permisos del usuario 5 en SucursalNorte
$gac->setEntity('user', 5)->setScope('SucursalNorte');
$p = $gac->getPermissions();

if ($p->get('users')?->hasFeature('create')) {
    echo "Puede crear usuarios en SucursalNorte\n";
}

// Verificar restricción horaria
$r = $gac->getRestrictions();
$resultado = $r->run(['date' => ['timestamp' => time()]]);

if (!$resultado->passed) {
    echo "Acceso denegado: {$resultado->message}\n";
}
```

Eso es todo. Las guías completas están en:

- **[docs/permissions.md](docs/permissions.md)** — Todo sobre permisos
- **[docs/restrictions.md](docs/restrictions.md)** — Todo sobre restricciones
- **[docs/database.md](docs/database.md)** — Drivers de base de datos (PDO, MySQLi, PostgreSQL)
- **[docs/cache.md](docs/cache.md)** — Sistema de caché (archivos, Redis, personalizado)

## Pruebas

```bash
php test/TestRunner.php
```

El script pide los datos de conexión a la base de datos y ejecuta 59 pruebas automáticas.

## Licencia

MIT
