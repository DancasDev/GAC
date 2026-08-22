# GAC — Granular Access Control

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

GAC (*Granular Access Control*) es una librería PHP para resolver la autorización y control de acceso en sistemas que requieren gestionar permisos y restricciones con granularidad por módulo, entidad y ámbito territorial.

---

## 🎯 Problemas que resuelve

1. **Permisología por Módulo y Acción (RBAC):**  
   Permite definir qué operaciones (`create`, `read`, `update`, `delete`, etc.) puede realizar una entidad (rol, usuario o cliente) sobre un módulo específico mediante máscaras de bits (*bitmask*).

2. **Ámbito y Jerarquía de Rutas (*Scope Paths*):**  
   Permite restringir el acceso a sucursales, departamentos o rutas específicas (ej: `/sucursal/1`, `/sucursal/2/*` o multi-rutas separadas por comas).

3. **Herencia Dinámica de Scope:**  
   Permite configurar las facultades de un rol de forma genérica (`scope_path = NULL`) y definir el alcance territorial específico en la asignación del usuario (`gac_role_entity.scope_path`).

4. **Restricciones Contextuales (ABAC):**  
   Permite bloquear accesos según condiciones del entorno de ejecución (dirección IP, franjas horarias o nombres de dominio) sin alterar las reglas de permisos base.

5. **Persistencia en Caché:**  
   Guarda la estructura final calculada de permisos y restricciones por entidad para evitar consultas repetitivas a la base de datos en cada petición.

---

## 📦 Instalación

```bash
composer require dancasdev/gac
```

---

## 💻 Uso Básico

```php
use DancasDev\GAC\GAC;
use DancasDev\GAC\Schema;

// 1. Crear el esquema de tablas (se ejecuta UNA SOLA VEZ en la instalación o migración inicial)
Schema::install($pdo);

// 2. Instanciar GAC con la conexión a la base de datos
$gac = new GAC($pdo);

// 3. Declarar la entidad (usuario ID: 10) y el scope activo
$gac->setEntity('user', 10)
    ->setScope('/sucursal/caracas');

// 4. Evaluar la autorización para un módulo y acción
if ($gac->can('caja_chica', 'read')) {
    // El usuario 10 tiene permiso para leer caja_chica en /sucursal/caracas
}
```

---

## 📚 Documentación Guiada (Paso a Paso)

Sigue la documentación en orden progresivo para aprender a usar la librería:

| Capítulo | Descripción |
|---|---|
| 🛡️ **[`docs/permissions.md`](docs/permissions.md)** | **Paso 1: Permisos (RBAC)** — Módulos, bitmasks, multi-scope y herencia dinámica. |
| ⛔ **[`docs/restrictions.md`](docs/restrictions.md)** | **Paso 2: Restricciones (ABAC)** — Bloqueos por IP, horario/fecha, dominio y diagnósticos. |
| 🔍 **[`docs/validation.md`](docs/validation.md)** | **Paso 3: Validación** — Sintaxis de scopes y prevención de duplicados en BD (`Utils`). |
| 💾 **[`docs/database.md`](docs/database.md)** | **Paso 4: Base de Datos** — Adaptadores (PDO, MySQLi, PgSQL) y frameworks. |
| ⚡ **[`docs/cache.md`](docs/cache.md)** | **Paso 5: Caché** — Configuración, drivers personalizados e invalidación. |

---

## 🧪 Pruebas

```bash
php test/TestRunner.php
```

---

## 📄 Licencia

MIT
