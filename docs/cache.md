# Guía: Sistema de Caché en GAC

GAC incluye un mecanismo de almacenamiento en caché para evitar realizar consultas a la base de datos en cada evaluación de permisos o restricciones.

---

## 1. Estructura de Almacenamiento (Versión 2)

GAC persiste en caché el resultado final procesado y deduplicado de cada entidad en una sola clave:

```json
{
  "_v": 2,
  "_ts": 1771780000,
  "p": {
    "caja_chica": [
      { "s": "/sucursal/caracas", "i": 101, "d": "0", "f": 7, "l": "1", "p": null },
      { "s": "/sucursal/maracaibo", "i": 102, "d": "0", "f": 7, "l": "1", "p": null }
    ]
  },
  "r": {
    "ip": [
      { "s": "*", "i": 45, "r": "allow", "c": { "list": ["192.168.1.*"] } }
    ]
  }
}
```

- **`_v`**: Versión del esquema de caché (permite invalidar automáticamente estructuras antiguas).
- **`_ts`**: Timestamp en el momento de generación.
- **`p`**: Permisos de la entidad agrupados por módulo.
- **`r`**: Restricciones de la entidad agrupadas por tipo.

---

## 2. Adaptadores de Caché

### 2.1 FileCache (Adaptador por Archivos)

Guarda las entradas en archivos JSON en una ruta local configurada:

```php
use DancasDev\GAC\GAC;

$gac = new GAC($pdo, [
    'driver' => 'file',
    'path'   => __DIR__ . '/writable/cache',
    'prefix' => 'gac',
    'ttl'    => 3600 // Tiempo de vida en segundos (1 hora)
]);
```

---

### 2.2 Adaptadores Personalizados (Redis, Memcached, etc.)

Cualquier sistema de almacenamiento puede integrarse implementando la interfaz `CacheInterface`:

```php
namespace DancasDev\GAC\Drivers\Cache;

interface CacheInterface {
    public function get(string $key): mixed;
    public function save(string $key, mixed $value, int $ttl = 0): bool;
    public function delete(string $key): bool;
    public function deleteMatching(string $pattern): bool;
    public function clean(): bool;
}
```

#### Ejemplo con Redis:

```php
use DancasDev\GAC\Drivers\Cache\CacheInterface;

class RedisCacheDriver implements CacheInterface {
    public function __construct(protected \Redis $redis) {}

    public function get(string $key): mixed {
        $data = $this->redis->get($key);
        return $data !== false ? json_decode($data, true) : null;
    }

    public function save(string $key, mixed $value, int $ttl = 0): bool {
        $json = json_encode($value);
        return $ttl > 0 ? $this->redis->setEx($key, $ttl, $json) : $this->redis->set($key, $json);
    }

    public function delete(string $key): bool {
        return $this->redis->del($key) > 0;
    }

    public function deleteMatching(string $pattern): bool {
        $keys = $this->redis->keys($pattern);
        return !empty($keys) ? $this->redis->del($keys) > 0 : true;
    }

    public function clean(): bool {
        return $this->redis->flushDB();
    }
}

// Inyección en GAC:
$gac = new GAC($pdo, new RedisCacheDriver($redisInstance));
```

---

## 3. Invalidación y Purga

Cuando se modifican permisos o roles en la base de datos, el caché de las entidades afectadas debe ser invalidado:

### 3.1 Purgar por Tipo de Entidad (`purgeCacheBy`)

```php
// Purgar caché de un usuario (ID: 10)
$gac->purgeCacheBy('user', [10]);

// Purgar caché de un cliente (ID: 25)
$gac->purgeCacheBy('client', [25]);

// Purgar caché de un rol (invalida a todos los usuarios y clientes asignados a ese rol)
$gac->purgeCacheBy('role', [5]);

// Purgar caché de restricciones globales
$gac->purgeCacheBy('global');
```

---

### 3.2 Limpiar la Entidad Activa (`clearCache`)

```php
// Limpia la caché de la entidad declarada en la instancia actual
$gac->clearCache();

// Limpia también las restricciones globales
$gac->clearCache(true);
```
