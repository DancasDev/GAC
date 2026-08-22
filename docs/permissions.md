# Permisos en GAC (RBAC)

Esta guía explica cómo funciona la permisología en GAC, desde la evaluación básica hasta la herencia dinámica y metadatos avanzados.

---

## 1. Evaluación Básica de Permisos

Para evaluar si una entidad (usuario o cliente) tiene autorización sobre un módulo en una ubicación o scope determinado:

```php
use DancasDev\GAC\GAC;

$gac = new GAC($pdo);

// 1. Declarar la entidad (ej: usuario ID 10) y el scope activo
$gac->setEntity('user', 10)
    ->setScope('/sucursal/caracas');

// 2. Preguntar por una acción específica
if ($gac->can('caja_chica', 'read')) {
    // El usuario puede leer en caja_chica para /sucursal/caracas
}
```

---

## 2. Acciones y Máscara de Bits (*Bitmask*)

Los permisos sobre un módulo no se almacenan como texto plano, sino como un número entero (*bitmask*). Cada acción corresponde a una potencia de 2:

| Acción | Valor | Descripción |
|---|---|---|
| **`create`** | **1** | Crear registros |
| **`read`** | **2** | Leer / consultar |
| **`update`** | **4** | Modificar existentes |
| **`delete`** | **8** | Eliminar registros |
| **`trash`** | **16** | Acceder a la papelera |
| **`dev`** | **32** | Acceso a funciones en desarrollo |

### Combinar Acciones
Para otorgar múltiples acciones, se suman los valores:
- `create` (1) + `read` (2) = **3**
- `create` (1) + `read` (2) + `update` (4) = **7**
- Control total (todas las acciones): **63**

### Evaluación en Código:
```php
// Evaluar una sola acción por nombre
$gac->can('caja_chica', 'read');

// Evaluar múltiples acciones obligatorias (AND)
$gac->can('caja_chica', ['create', 'update']);

// Evaluar mediante el número de bitmask directamente
$gac->can('caja_chica', 7);
```

---

## 3. Scopes y Jerarquía de Rutas

Los *scopes* permiten definir dónde aplica un permiso.

- **`*` (Global):** Aplica a cualquier ámbito o sucursal.
- **`/sucursal/caracas` (Ruta Exacta):** Aplica únicamente a esa ubicación.
- **`/sucursal/caracas/*` (Wildcard):** Aplica a Caracas y a todas sus sub-ubicaciones.
- **`/sucursal/1,/sucursal/2` (Multi-Path):** Permite autorizar varias ubicaciones en un solo registro separadas por comas.

---

## 4. Herencia Dinámica de Scope

En sistemas multi-sucursal, los roles suelen definir **qué se puede hacer**, mientras que la asignación del usuario define **dónde se puede hacer**.

### Cómo funciona:
1. En la tabla `gac_permission`, el rol define el módulo y las acciones, pero deja `scope_path = NULL`.
2. En la tabla `gac_role_entity`, se asigna el rol al usuario y se especifica su `scope_path` (ej: `/sucursal/caracas,/sucursal/maracaibo`).

```
Permiso del Rol 'Cajero':
  module: 'caja_chica', feature: 7, scope_path: NULL

Asignación a Usuario 'Juan':
  role: 'Cajero', scope_path: '/sucursal/caracas,/sucursal/maracaibo'

Resultado:
  Juan en '/sucursal/caracas'   -> true
  Juan en '/sucursal/maracaibo' -> true
  Juan en '/sucursal/valencia'  -> false
```

---

## 5. Metadatos Adicionales (*Payload*)

Un permiso puede almacenar metadatos JSON arbitrarios en la columna `payload` para que el controlador aplique filtros adicionales:

```php
$permission = $gac->getPermission('archivos');

if ($permission !== null) {
    $payload = $permission->getPayload(); // ej: ['locker_ids' => [1, 2]]
}
```

---

## 6. Estructura del Esquema de Base de Datos

Las tablas necesarias para la permisología se crean una sola vez con `Schema::install($pdo)`:

- **`gac_module_category`**: Categorías de módulos (ej: `finanzas`).
- **`gac_module`**: Módulos individuales (ej: `caja_chica`).
- **`gac_role`**: Catálogo de roles.
- **`gac_role_entity`**: Asignación de roles a entidades con prioridad y `scope_path`.
- **`gac_permission`**: Permisos asignados a roles, usuarios o clientes.
