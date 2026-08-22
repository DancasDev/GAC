# Guía: Conexión y Drivers de Base de Datos

GAC es agnóstico al motor de base de datos y al framework web. Admite **MySQL (5.7+)** y **PostgreSQL (12+)** a través de una interfaz de abstracción liviana.

---

## 1. Adaptadores Nativos Incluidos

| Adaptador | Driver Envuelto | Motor BD | Placeholder SQL |
|---|---|---|---|
| **`PdoConnection`** | `\PDO` | MySQL, PostgreSQL, etc. | `?` |
| **`MysqliConnection`** | `\mysqli` | MySQL | `?` |
| **`PgsqlConnection`** | `\PgSql\Connection` | PostgreSQL | `$1`, `$2`, ... |

---

## 2. Uso con PHP Puro

### Con PDO (Recomendado):
```php
use DancasDev\GAC\GAC;

$pdo = new PDO('mysql:host=localhost;dbname=mi_app;charset=utf8mb4', 'root', '');
$gac = new GAC($pdo);
```

### Con MySQLi Nativo:
```php
use DancasDev\GAC\GAC;
use DancasDev\GAC\Drivers\Database\MysqliConnection;

$mysqli = new mysqli('localhost', 'root', '', 'mi_app');
$gac = new GAC(new MysqliConnection($mysqli));
```

### Con PostgreSQL Nativo:
```php
use DancasDev\GAC\GAC;
use DancasDev\GAC\Drivers\Database\PgsqlConnection;

$pg = pg_connect('host=localhost dbname=mi_app user=postgres password=secret');
$gac = new GAC(new PgsqlConnection($pg));
```

---

## 3. Integración con Frameworks

### Laravel (Eloquent / DB Facade):
```php
use DancasDev\GAC\GAC;
use DancasDev\GAC\Drivers\Database\PdoConnection;
use Illuminate\Support\Facades\DB;

$gac = new GAC(new PdoConnection(DB::connection()->getPdo()));
```

### CodeIgniter 4:
```php
use DancasDev\GAC\GAC;
use DancasDev\GAC\Drivers\Database\MysqliConnection;
use DancasDev\GAC\Drivers\Database\PgsqlConnection;

$db = \Config\Database::connect();

// Con driver MySQLi en CI4:
$gac = new GAC(new MysqliConnection($db->connID));

// Con driver Postgre en CI4:
$gac = new GAC(new PgsqlConnection($db->connID));
```

### Symfony / Doctrine ORM:
```php
use DancasDev\GAC\GAC;
use DancasDev\GAC\Drivers\Database\PdoConnection;

$pdo = $entityManager->getConnection()->getNativeConnection();
$gac = new GAC(new PdoConnection($pdo));
```

---

## 4. Instalación del Esquema con `Schema`

La clase `Schema` acepta cualquier adaptador de conexión o instancia directa de `PDO`:

```php
use DancasDev\GAC\Schema;

// Crear todas las tablas
Schema::install($connection);

// Eliminar solo las tablas de GAC
Schema::uninstall($connection);
```
