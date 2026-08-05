# Drivers de Base de Datos

GAC se conecta a la base de datos a través de una abstracción que permite usar
**cualquier conexión existente** sin importar el framework o el driver nativo.

---

## 1. Interfaces

### `ConnectionInterface`

Contrato que un driver de base de datos debe implementar:

| Método | Descripción | Retorna |
|--------|-------------|---------|
| `param(?int $index)` | Placeholder para parámetros en SQL | `string` |
| `prepare(string $query)` | Prepara una sentencia SQL | `StatementInterface\|false` |
| `exec(string $statement)` | Ejecuta una sentencia sin resultado (DDL) | `int\|false` |
| `lastInsertId(?string $name)` | ID de la última inserción | `string\|false` |

### `StatementInterface`

Contrato que un statement preparado debe implementar:

| Método | Descripción | Retorna |
|--------|-------------|---------|
| `execute(?array $params)` | Ejecuta con parámetros | `bool` |
| `fetchAll(int $mode)` | Obtiene todos los registros | `array` |

Constante `FETCH_ASSOC = 2` (mismo valor que `PDO::FETCH_ASSOC`).

---

## 2. Adapters incluidos

La librería incluye tres adapters listos para usar:

| Adapter | Envuelve | Driver | Placeholder |
|---------|----------|--------|-------------|
| `PdoConnection` | `PDO` | MySQL, PostgreSQL, etc. | `?` |
| `MysqliConnection` | `\mysqli` | MySQL | `?` |
| `PgsqlConnection` | `\PgSql\Connection` | PostgreSQL | `$1`, `$2`… |

### `param()` — placeholders

Cada adapter genera el placeholder correcto según el driver nativo:

```php
// PdoConnection / MysqliConnection → siempre "?"
$conn->param()  // "?"
$conn->param()  // "?"
$conn->param(0) // "?" (índice explícito)

// PgsqlConnection → auto-incremental "$1", "$2"…
$conn->param()  // "$1"
$conn->param()  // "$2"
$conn->param(0) // "$1" (índice explícito, no modifica el contador)
$conn->param()  // "$3"
```

El contador se reinicia automáticamente al llamar a `prepare()`.

---

## 3. Uso básico

```php
use DancasDev\GAC\GAC;

// PHP puro — con PDO (sigue funcionando igual)
$gac = new GAC($pdo);

// PHP puro — con mysqli nativo
$mysqli = new mysqli('localhost', 'root', '', 'basedatos');
$gac = new GAC(new \DancasDev\GAC\Drivers\Database\MysqliConnection($mysqli));

// PHP puro — con pgsql nativo
$pgsql = pg_connect('host=localhost dbname=basedatos user=postgres');
$gac = new GAC(new \DancasDev\GAC\Drivers\Database\PgsqlConnection($pgsql));
```

---

## 4. Uso con frameworks

### Laravel

```php
$gac = new GAC(new \DancasDev\GAC\Drivers\Database\PdoConnection(
    \DB::connection()->getPdo()
));
```

### Symfony / Doctrine

```php
$gac = new GAC(new \DancasDev\GAC\Drivers\Database\PdoConnection(
    $entityManager->getConnection()->getNativeConnection()
));
```

### CodeIgniter 4 — MySQLi

```php
$db = \Config\Database::connect();
$gac = new GAC(new \DancasDev\GAC\Drivers\Database\MysqliConnection(
    $db->connID
));
```

### CodeIgniter 4 — PostgreSQL

```php
$db = \Config\Database::connect();
$gac = new GAC(new \DancasDev\GAC\Drivers\Database\PgsqlConnection(
    $db->connID
));
```

---

## 5. Schema con adapters

`Schema::install()` y `Schema::uninstall()` también aceptan cualquier adapter:

```php
use DancasDev\GAC\Schema;
use DancasDev\GAC\Drivers\Database\MysqliConnection;

$mysqli = new mysqli('localhost', 'root', '', 'basedatos');
$conn = new MysqliConnection($mysqli);

Schema::install($conn);     // Crea las tablas
Schema::uninstall($conn);   // Elimina solo las tablas GAC
```

> **Nota:** Si se pasa un `PDO` directamente, Schema lo envuelve automáticamente
> en un `PdoConnection`. No es necesario hacerlo manualmente.
