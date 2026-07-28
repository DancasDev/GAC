# Caché

GAC incluye un sistema de caché para evitar consultas repetitivas a la base de
datos. Está diseñado para ser reemplazable: puede usar el driver incluido
basado en archivos o implementar su propio driver (Redis, Memcached, etc.).

---

## 1. CacheInterface

Contrato que cualquier driver de caché debe implementar:

| Método | Descripción | Retorna |
|--------|-------------|---------|
| `get(string $key)` | Obtiene un valor del caché | `mixed` |
| `save(string $key, mixed $data, ?int $ttl)` | Almacena un valor con tiempo de vida (segundos) | `bool` |
| `delete(string $key)` | Elimina un valor | `bool` |
| `deleteMatching(string $pattern)` | Elimina todos los valores que coincidan con un patrón glob | `int` |
| `clean()` | Elimina todos los valores del caché | `bool` |

---

## 2. FileCache — driver por defecto

Almacena los valores en archivos JSON individuales dentro de un directorio.
Cada archivo contiene el valor serializado y su fecha de expiración.

```php
use DancasDev\GAC\Drivers\Cache\FileCache;

// Especificando el directorio
$cache = new FileCache('/ruta/al/cache');

// También se puede establecer después
$cache = new FileCache();
$cache->setDir('/ruta/al/cache');

// Guardar con TTL de 5 minutos
$cache->save('mi_clave', $datos, 300);

// Recuperar
$datos = $cache->get('mi_clave');  // null si no existe o expiró

// Eliminar
$cache->delete('mi_clave');

// Limpiar todo
$cache->clean();
```

---

## 3. Usar caché con GAC

### Con configuración automática (archivos)

```php
$gac = new GAC($pdo, [
    'dir'    => __DIR__ . '/cache',
    'prefix' => 'gac',
    'ttl'    => 1800,      // 30 minutos
]);
```

Si no se especifica `dir`, se usa `src/writable/`.

### Con un driver personalizado

```php
$gac = new GAC($pdo, new MiCacheRedis());
```

GAC detecta automáticamente si el segundo parámetro es un array (configuración
de FileCache) o una instancia de `CacheInterface` (driver personalizado).

---

## 4. Crear un driver personalizado

Implemente `CacheInterface` para usar Redis, Memcached, MySQL, o cualquier
otro sistema de almacenamiento:

```php
use DancasDev\GAC\Drivers\Cache\CacheInterface;

class RedisCache implements CacheInterface {
    private \Redis $redis;

    public function __construct(\Redis $redis) {
        $this->redis = $redis;
    }

    public function get(string $key): mixed {
        $data = $this->redis->get($key);
        return $data === false ? null : json_decode($data, true);
    }

    public function save(string $key, mixed $data, ?int $ttl = 60): bool {
        $encoded = json_encode($data);
        if ($ttl) {
            return $this->redis->setex($key, $ttl, $encoded);
        }
        return $this->redis->set($key, $encoded);
    }

    public function delete(string $key): bool {
        return $this->redis->del($key) > 0;
    }

    public function deleteMatching(string $pattern): int {
        $keys = $this->redis->keys($pattern);
        if (empty($keys)) return 0;
        return $this->redis->del($keys);
    }

    public function clean(): bool {
        $this->redis->flushDB();
        return true;
    }
}
```

```php
$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);

$gac = new GAC($pdo, new RedisCache($redis));
```

---

## 5. Limpiar caché desde GAC

```php
// Limpiar caché de la entidad actual
$gac->clearCache();

// Limpiar también el caché global (restricciones globales)
$gac->clearCache(true);
```

---

## 6. Purgar caché por entidad

Como permisos y restricciones de una entidad comparten la misma clave de caché,
un solo método unificado los purga a ambos:

```php
// Purgar caché de un usuario específico
$gac->purgeCacheBy('user', [30]);

// Purgar caché de un rol (todos los usuarios con ese rol)
$gac->purgeCacheBy('role', [1]);

// Purgar caché de restricciones globales
$gac->purgeCacheBy('global');
```
