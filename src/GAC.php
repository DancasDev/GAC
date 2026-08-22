<?php

namespace DancasDev\GAC;

use DancasDev\GAC\Permissions\Permissions;
use DancasDev\GAC\Permissions\Permission;
use DancasDev\GAC\Restrictions\Restrictions;
use DancasDev\GAC\Restrictions\RestrictionResult;
use DancasDev\GAC\Drivers\Cache\CacheInterface;
use DancasDev\GAC\Drivers\Database\ConnectionInterface;
use DancasDev\GAC\Drivers\Database\StatementInterface;
use DancasDev\GAC\Drivers\Database\PdoConnection;
use DancasDev\GAC\Utils;
use PDO;

class GAC {
    public const CACHE_SCHEMA_VERSION = 2;

    public ?CacheInterface $cacheAdapter = null;

    protected ConnectionInterface $connection;
    protected array $entityTypeKeys = ['user' => '1', 'client' => '2'];
    protected array $entityRoleData = [];
    protected $entityType;
    protected $entityId;
    protected $scopePath = '*';

    protected $cacheTtl;
    protected $cachekey;

    protected array $entityCache = [];
    protected bool $entityLoaded = false;
    protected array $globalRestrictions = [];
    protected bool $globalLoaded = false;

    public function __construct(ConnectionInterface|PDO $connection, CacheInterface|array|null $cache = null) {
        if ($connection instanceof PDO) {
            $connection = new PdoConnection($connection);
        }
        $this->connection = $connection;
        $this->cachekey = 'gac';
        $this->cacheTtl = 1800;

        if ($cache instanceof CacheInterface) {
            $this->cacheAdapter = $cache;
        } elseif (is_array($cache)) {
            $dir = $cache['dir'] ?? ($cache['path'] ?? __DIR__ . '/writable');
            $this->cacheAdapter = new \DancasDev\GAC\Drivers\Cache\FileCache($dir);
            $this->cachekey = $cache['prefix'] ?? 'gac';
            $this->cacheTtl = (int) ($cache['ttl'] ?? 1800);
        }
    }

    public function setEntity(string $entityType, string|int $entityId): GAC {
        $this->entityType = (string) ($this->entityTypeKeys[$entityType] ?? $entityType);
        $this->entityId = $entityId;
        $this->entityRoleData = [];
        $this->entityCache = [];
        $this->entityLoaded = false;
        return $this;
    }

    public function setScope(string $scopePath): GAC {
        if (!Utils::scopeValidate($scopePath)) {
            throw new \InvalidArgumentException("Invalid scope path: '$scopePath'");
        }
        $this->scopePath = $scopePath;
        return $this;
    }

    public function getScope(): string {
        return $this->scopePath;
    }

    public function setCacheTtl(int $ttl): GAC {
        $this->cacheTtl = $ttl;
        return $this;
    }

    public function setCacheKey(string $prefix): GAC {
        $this->cachekey = $prefix;
        return $this;
    }

    public function getEntityType(): string {
        return $this->entityType;
    }

    public function getEntityId(): string|int {
        return $this->entityId;
    }

    public function getCacheKey(): string {
        return $this->cachekey . '_' . $this->entityType . '_' . $this->entityId;
    }

    public function getGlobalCacheKey(): string {
        return $this->cachekey . '_global';
    }

    /**
     * Obtiene la instancia resuelta de Permission para un módulo bajo el scope activo ($this->scopePath).
     *
     * @param string $moduleCode Código del módulo
     * @param bool $fromCache Si debe resolver usando el caché de la entidad
     * @return Permission|null Instancia resuelta de Permission o NULL si no posee autorización
     */
    public function getPermission(string $moduleCode, bool $fromCache = true): ?Permission {
        return $this->getPermissions($fromCache)->get($moduleCode);
    }

    /**
     * Evalúa las restricciones aplicables sobre un contexto para el scope activo ($this->scopePath)
     * y retorna el resultado detallado de la validación.
     *
     * @param array $context Contexto de ejecución a evaluar (ej: ['ip' => [...], 'date' => [...]])
     * @param bool $fromCache Si debe resolver usando el caché de la entidad
     * @return RestrictionResult Objeto con el resultado detallado de la evaluación de restricciones
     */
    public function getRestrictionResult(array $context, bool $fromCache = true): RestrictionResult {
        return $this->getRestrictions($fromCache)->run($context);
    }

    /**
     * Evalúa si la entidad actual tiene autorización para un módulo y característica bajo el scope activo.
     * Resuelve: (Permisos Base + Herencia de Scope) - (Restricciones Aplicables).
     *
     * Principio de Menor Privilegio (Cero Brechas):
     * Ante cualquier fallo, excepción o falta de coincidencia, retorna FALSE.
     *
     * @param string $module Código del módulo
     * @param string|array|int $feature Característica requerida ('read', 'create', bitmask, etc.)
     * @param array|null $context Contexto de restricciones opcional
     * @return bool TRUE si está autorizado, FALSE si se deniega
     */
    public function can(string $module, string|array|int $feature, ?array $context = null): bool {
        try {
            $perm = $this->getPermission($module);

            if ($perm === null) {
                return false;
            }

            if (!$perm->hasFeature($feature)) {
                return false;
            }

            if ($context !== null && !empty($context)) {
                if ($this->isRestricted($context)) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Evalúa si el contexto de ejecución está bloqueado por alguna restricción activa bajo el scope activo.
     *
     * Principio de Menor Privilegio:
     * Ante cualquier fallo de evaluación o error en contexto, retorna TRUE (bloqueado).
     *
     * @param array|null $context Contexto de ejecución
     * @return bool TRUE si existe restricción activa (bloqueo), FALSE si pasa todas las restricciones
     */
    public function isRestricted(?array $context = null): bool {
        try {
            if ($context === null || empty($context)) {
                return false;
            }

            $result = $this->getRestrictionResult($context);
            return !$result->passed;
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Obtiene los permisos del usuario/cliente resueltos para el scope activo ($this->scopePath).
     * Resuelve scope, prioridad y granularidad módulo por módulo.
     */
    public function getPermissions(bool $fromCache = true): Permissions {
        $this->ensureLoaded($fromCache);

        $result = [];
        foreach (($this->entityCache['p'] ?? []) as $module => $records) {
            $best = $this->resolveScope($records);
            if ($best !== null) {
                unset($best['s']);
                $result[$module] = $best;
            }
        }

        return new Permissions($result);
    }

    /**
     * Obtiene las restricciones del usuario/cliente + globales resueltas para el scope activo ($this->scopePath).
     * Primero resuelve scope para restricciones de entidad,
     * luego agrega globales solo para tipos no definidos.
     */
    public function getRestrictions(bool $fromCache = true): Restrictions {
        if (empty($this->entityType) || empty($this->entityId)) {
            throw new \Exception('Entity type and ID must be set before loading data.', 1);
        }

        $this->ensureLoaded($fromCache);
        $this->ensureGlobalRestrictions($fromCache);

        $data = [];
        foreach (($this->entityCache['r'] ?? []) as $type => $records) {
            $best = $this->resolveScope($records);
            if ($best !== null) {
                $data[$type] = [$best];
            }
        }

        // Globales: resuelven scope y solo aplican si la entidad no definió reglas para ese tipo
        foreach ($this->globalRestrictions as $type => $rules) {
            if (!isset($data[$type])) {
                $best = $this->resolveScope($rules);
                if ($best !== null) {
                    $data[$type] = [$best];
                }
            }
        }

        return new Restrictions($data);
    }

    /**
     * Exporta el arreglo crudo con todos los permisos de la entidad (con todos sus scopes) sin filtrar.
     * Útil para exportación e hidratación en aplicaciones cliente (ej. librerías JavaScript).
     *
     * @param bool $fromCache Si debe resolver desde el caché de la entidad
     * @return array Arreglo crudo de permisos: array[module_code][] = {s, i, d, f, l, p}
     */
    public function exportPermissions(bool $fromCache = true): array {
        $this->ensureLoaded($fromCache);
        return $this->entityCache['p'] ?? [];
    }

    /**
     * Exporta el arreglo crudo con todas las restricciones de la entidad (incluyendo globales) sin filtrar por scope.
     * Útil para exportación e hidratación en aplicaciones cliente (ej. librerías JavaScript).
     *
     * @param bool $fromCache Si debe resolver desde el caché de la entidad
     * @return array Arreglo crudo de restricciones: array[type][] = {s, i, r, c}
     */
    public function exportRestrictions(bool $fromCache = true): array {
        $this->ensureLoaded($fromCache);
        $this->ensureGlobalRestrictions($fromCache);

        $data = $this->entityCache['r'] ?? [];
        foreach ($this->globalRestrictions as $type => $rules) {
            if (!isset($data[$type])) {
                $data[$type] = $rules;
            }
        }
        return $data;
    }

    // ─── Cache ────────────────────────────────────────

    /**
     * Carga perezosa de permisos + restricciones de entidad.
     * Intenta cache primero, si falla ejecuta consultas y persiste ÚNICAMENTE el payload final calculated.
     */
    protected function ensureLoaded(bool $fromCache): void {
        if ($this->entityLoaded) return;
        if (empty($this->entityType) || empty($this->entityId)) {
            throw new \Exception('Entity type and ID must be set before loading data.', 1);
        }

        if ($fromCache && $this->cacheAdapter) {
            $cached = $this->cacheAdapter->get($this->getCacheKey());
            if (is_array($cached) && isset($cached['_v'], $cached['p'], $cached['r']) && $cached['_v'] === self::CACHE_SCHEMA_VERSION) {
                $this->entityCache = $cached;
                $this->entityLoaded = true;
                return;
            }
        }

        $this->entityCache = [
            '_v'  => self::CACHE_SCHEMA_VERSION,
            '_ts' => time(),
            'p'   => $this->getPermissionsFromDB(),
            'r'   => $this->getEntityRestrictionsFromDB(),
        ];
        $this->entityLoaded = true;

        if ($this->cacheAdapter) {
            $this->cacheAdapter->save($this->getCacheKey(), $this->entityCache, $this->cacheTtl);
        }
    }

    /**
     * Carga perezosa de restricciones globales (entity_type='3').
     * Cache separado para no invalidar todas las entidades al cambiar globales.
     */
    protected function ensureGlobalRestrictions(bool $fromCache): void {
        if ($this->globalLoaded) return;

        if ($fromCache && $this->cacheAdapter) {
            $cached = $this->cacheAdapter->get($this->getGlobalCacheKey());
            if (is_array($cached) && isset($cached['_v'], $cached['d']) && $cached['_v'] === self::CACHE_SCHEMA_VERSION) {
                $this->globalRestrictions = $cached['d'];
                $this->globalLoaded = true;
                return;
            }
        }

        $this->globalRestrictions = $this->getGlobalRestrictionsFromDB();
        $this->globalLoaded = true;

        if ($this->cacheAdapter) {
            $this->cacheAdapter->save($this->getGlobalCacheKey(), [
                '_v'  => self::CACHE_SCHEMA_VERSION,
                '_ts' => time(),
                'd'   => $this->globalRestrictions,
            ], $this->cacheTtl);
        }
    }

    public function clearCache(bool $includeGlobal = false): bool {
        if (empty($this->cacheAdapter)) {
            return false;
        }

        $this->cacheAdapter->delete($this->getCacheKey());

        if ($includeGlobal) {
            $this->cacheAdapter->delete($this->getGlobalCacheKey());
        }

        return true;
    }

    public function purgeCacheBy(string $entityType, array $entityIds = []): bool {
        if (empty($this->cacheAdapter)) {
            return false;
        }

        if ($entityType === 'global') {
            $this->cacheAdapter->delete($this->getGlobalCacheKey());
            return true;
        }

        if (empty($entityIds)) {
            return false;
        }

        $list = [];
        if ($entityType === 'user') {
            $list['1'] = $entityIds;
        } elseif ($entityType === 'client') {
            $list['2'] = $entityIds;
        } elseif ($entityType === 'role') {
            $result = $this->getEntitiesByRoleIds($entityIds);
            foreach ($result as $record) {
                $list[$record['entity_type']] ??= [];
                $list[$record['entity_type']][$record['entity_id']] = $record['entity_id'];
            }
        }

        foreach ($list as $entityKey => $subList) {
            foreach ($subList as $entityId) {
                $this->cacheAdapter->delete($this->cachekey . '_' . $entityKey . '_' . $entityId);
            }
        }

        return true;
    }

    // ─── Consultas a base de datos ────────────────────────────────────────

    /**
     * Expande un string multi-path separado por comas o aplica la herencia dinámica
     * desde el scope asignado en `gac_role_entity`.
     */
    protected function expandScopePath(?string $permScope, ?string $entityRoleScope): array {
        if ($permScope === null || trim($permScope) === '') {
            if ($entityRoleScope === null || trim($entityRoleScope) === '') {
                return ['*'];
            }
            $raw = $entityRoleScope;
        } else {
            $raw = $permScope;
        }

        $parts = explode(',', $raw);
        $result = [];
        foreach ($parts as $p) {
            $trimmed = trim($p);
            if ($trimmed !== '') {
                $result[] = $trimmed;
            }
        }

        return !empty($result) ? array_values(array_unique($result)) : ['*'];
    }

    /**
     * Obtiene y procesa los permisos desde la base de datos aplicando herencia y multi-path.
     */
    protected function getPermissionsFromDB(): array {
        $response = [];

        if (empty($this->entityType) || $this->entityType === '0') {
            return $response;
        }

        $roleData = $this->getEntityRoleData();
        $c = $this->connection;

        $query = 'SELECT id, entity_type, entity_id, module_id, scope_path, feature, level, payload';
        $query .= ' FROM gac_permission WHERE ((entity_type = ' . $c->param() . ' AND entity_id = ' . $c->param() . ')';
        foreach ($roleData['list'] as $id) {
            $query .= ' OR (entity_type = \'0\' AND entity_id = ' . $c->param() . ')';
        }
        $query .= ') AND deleted_at IS NULL AND is_disabled = \'0\'';
        $query .= ' ORDER BY entity_type DESC';
        $stmt = $c->prepare($query);
        $stmt->execute(array_merge([$this->entityType, $this->entityId], $roleData['list']));
        $result = $stmt->fetchAll(StatementInterface::FETCH_ASSOC);

        if (!is_array($result) || empty($result)) {
            return $response;
        }

        $moduleIds = [];
        $permissions = [];
        foreach ($result as $record) {
            $moduleIds[$record['module_id']] = $record['module_id'];

            $record['feature'] = (int) ($record['feature'] ?? 0);
            $record['level'] = (int) $record['level'];
            $record['payload'] = $this->decodePayload($record['payload'] ?? null);

            if ($record['entity_type'] !== '0') {
                $priority = -1;
                $entityRoleScope = null;
            } else {
                $roleId = (int) $record['entity_id'];
                $priority = $roleData['priority'][$roleId] ?? 100;
                $entityRoleScope = $roleData['scope'][$roleId] ?? null;
            }

            $paths = $this->expandScopePath($record['scope_path'] ?? null, $entityRoleScope);

            foreach ($paths as $path) {
                $permissions[] = [
                    'id'         => $record['id'],
                    'module_id'  => $record['module_id'],
                    'priority'   => $priority,
                    'scope_path' => $path,
                    'feature'    => $record['feature'],
                    'level'      => $record['level'],
                    'payload'    => $record['payload'],
                ];
            }
        }

        $modulesBy = [];
        if (!empty($moduleIds)) {
            $query = 'SELECT a.id, a.code, a.is_developing FROM gac_module AS a INNER JOIN gac_module_category AS b ON a.module_category_id = b.id';
            $query .= ' WHERE a.id IN (' . implode(',', $moduleIds) . ')';
            $query .= ' AND a.deleted_at IS NULL AND b.deleted_at IS NULL AND a.is_disabled = \'0\' AND b.is_disabled = \'0\'';

            $stmt = $this->connection->prepare($query);
            $stmt->execute();
            $result = $stmt->fetchAll(StatementInterface::FETCH_ASSOC);

            foreach ($result as $record) {
                $modulesBy[$record['id']] = $record;
            }
        }

        if (!empty($roleData['priority'])) {
            usort($permissions, function(array $a, array $b) {
                return $a['priority'] <=> $b['priority'];
            });
        }

        // Dedup por (module_code, scope_path)
        $dedupMap = [];
        foreach ($permissions as $permission) {
            $moduleData = $modulesBy[$permission['module_id']] ?? null;
            if ($moduleData === null) continue;

            $code = $moduleData['code'];
            $scope = $permission['scope_path'] ?? '*';
            $dupKey = $code . '|' . $scope;

            if (!isset($dedupMap[$dupKey])) {
                $dedupMap[$dupKey] = true;
                $response[$code][] = [
                    's' => $scope,
                    'i' => $permission['id'],
                    'd' => $moduleData['is_developing'],
                    'f' => $permission['feature'],
                    'l' => $permission['level'],
                    'p' => $permission['payload']
                ];
            }
        }

        return $response;
    }

    protected function decodePayload(?string $json): ?array {
        if ($json === null || $json === '') return null;
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Obtiene y procesa las restricciones de la entidad desde la base de datos aplicando herencia y multi-path.
     */
    protected function getEntityRestrictionsFromDB(): array {
        $roleData = $this->getEntityRoleData();
        $c = $this->connection;

        $query = 'SELECT id, entity_type, entity_id, scope_path, type, rule, config';
        $query .= ' FROM gac_restriction';
        $query .= ' WHERE deleted_at IS NULL AND is_disabled = \'0\'';
        $query .= ' AND ((entity_type = ' . $c->param() . ' AND entity_id = ' . $c->param() . ')';
        foreach ($roleData['list'] as $id) {
            $query .= ' OR (entity_type = \'0\' AND entity_id = ' . $c->param() . ')';
        }
        $query .= ')';
        $query .= ' ORDER BY entity_type DESC';

        $params = array_merge([$this->entityType, $this->entityId], $roleData['list']);
        $stmt = $c->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll(StatementInterface::FETCH_ASSOC);

        if (!is_array($result) || empty($result)) {
            return [];
        }

        // Asignar prioridad y expandir scopes
        $records = [];
        $entityTypeKey = (string) $this->entityType;
        foreach ($result as $record) {
            if ($record['entity_type'] === $entityTypeKey) {
                $priority = -1;
                $entityRoleScope = null;
            } else {
                $roleId = (int) $record['entity_id'];
                $priority = $roleData['priority'][$roleId] ?? 100;
                $entityRoleScope = $roleData['scope'][$roleId] ?? null;
            }

            $paths = $this->expandScopePath($record['scope_path'] ?? null, $entityRoleScope);
            $config = @json_decode($record['config'], true) ?? [];

            foreach ($paths as $path) {
                $records[] = [
                    'id'          => $record['id'],
                    'priority'    => $priority,
                    'entity_type' => $record['entity_type'],
                    'entity_id'   => $record['entity_id'],
                    'scope_path'  => $path,
                    'type'        => $record['type'],
                    'r'           => $record['rule'],
                    'config'      => $config
                ];
            }
        }

        // Ordenar por prioridad
        usort($records, function(array $a, array $b) {
            return $a['priority'] <=> $b['priority'];
        });

        // Eliminar duplicados por tipo (la primera entidad por tipo gana)
        $response = [];
        $reservationList = [];
        foreach ($records as $rec) {
            $entityKey = $rec['entity_type'] . '_' . $rec['entity_id'];
            $reservationList[$rec['type']] ??= $entityKey;
            if ($reservationList[$rec['type']] !== $entityKey) {
                continue;
            }

            $response[$rec['type']][] = [
                's' => $rec['scope_path'],
                'i' => $rec['id'],
                'r' => $rec['r'],
                'c' => $rec['config']
            ];
        }

        return $response;
    }

    protected function getGlobalRestrictionsFromDB(): array {
        $query = 'SELECT id, entity_type, entity_id, scope_path, type, rule, config';
        $query .= ' FROM gac_restriction';
        $query .= ' WHERE deleted_at IS NULL AND is_disabled = \'0\'';
        $query .= ' AND entity_type = \'3\'';

        $stmt = $this->connection->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(StatementInterface::FETCH_ASSOC);

        if (!is_array($result) || empty($result)) {
            return [];
        }

        $response = [];
        foreach ($result as $record) {
            $paths = $this->expandScopePath($record['scope_path'] ?? null, null);
            $config = @json_decode($record['config'], true) ?? [];
            foreach ($paths as $path) {
                $response[$record['type']][] = [
                    's' => $path,
                    'i' => $record['id'],
                    'r' => $record['rule'],
                    'c' => $config
                ];
            }
        }

        return $response;
    }

    protected function getEntitiesByRoleIds(array $roleIds): array {
        if (empty($roleIds)) {
            return [];
        }

        $c = $this->connection;
        $roleIds = array_map('intval', $roleIds);
        $parts = [];
        foreach ($roleIds as $id) {
            $parts[] = $c->param();
        }
        $placeholders = implode(',', $parts);
        $query = 'SELECT id, role_id, entity_type, entity_id FROM gac_role_entity WHERE role_id IN (' . $placeholders . ') AND is_disabled = \'0\' AND deleted_at IS NULL';
        $stmt = $c->prepare($query);
        $stmt->execute($roleIds);

        return $stmt->fetchAll(StatementInterface::FETCH_ASSOC);
    }

    protected function getEntityRoleData(bool $reset = false): array {
        if ($reset || empty($this->entityRoleData)) {
            $data = ['list' => [], 'priority' => [], 'scope' => []];
            $c = $this->connection;
            $query = 'SELECT b.id, a.priority, a.scope_path';
            $query .= ' FROM gac_role_entity AS a INNER JOIN gac_role AS b ON a.role_id = b.id';
            $query .= ' WHERE a.entity_type = ' . $c->param() . ' AND a.entity_id = ' . $c->param() . ' AND a.is_disabled = \'0\' AND b.is_disabled = \'0\' AND a.deleted_at IS NULL AND b.deleted_at IS NULL';
            $query .= ' ORDER BY a.priority ASC';
            $stmt = $c->prepare($query);
            $stmt->execute([$this->entityType, $this->entityId]);
            $result = $stmt->fetchAll(StatementInterface::FETCH_ASSOC);

            foreach ($result as $role) {
                $roleId = (int) $role['id'];
                $data['priority'][$roleId] = (int) $role['priority'];
                $data['scope'][$roleId] = ($role['scope_path'] !== null && trim($role['scope_path']) !== '') ? (string) $role['scope_path'] : null;
                $data['list'][] = $roleId;
            }

            $this->entityRoleData = $data;
        }

        return $this->entityRoleData;
    }

    // ─── Utilidades ────────────────────────────────────────

    /**
     * Dado un conjunto de registros con distintos scopes (campo 's'),
     * elige cuál aplica para el scopePath actual ($this->scopePath).
     */
    protected function resolveScope(array $records, ?string $scopePath = null): ?array {
        $current = $scopePath ?? $this->scopePath;
        $exact = $global = null;
        $wildcards = [];

        foreach ($records as $rec) {
            $s = $rec['s'] ?? '*';

            if ($s === $current) {
                $exact = $rec;
            } elseif ($s === '*' && $global === null) {
                $global = $rec;
            } elseif (str_ends_with($s, '/*')) {
                $prefix = substr($s, 0, -2);
                $depth = substr_count($prefix, '/');
                if ($current === $prefix || str_starts_with($current . '/', $prefix . '/')) {
                    if (!isset($wildcards[$depth])) {
                        $wildcards[$depth] = $rec;
                    }
                }
            }
        }

        if ($exact) return $exact;

        if (!empty($wildcards)) {
            krsort($wildcards);
            return reset($wildcards);
        }

        return $global;
    }
}
