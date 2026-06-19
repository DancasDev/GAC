<?php

namespace DancasDev\GAC;

use DancasDev\GAC\Permissions\Permissions;
use DancasDev\GAC\Restrictions\Restrictions;
use DancasDev\GAC\Drivers\CacheAdapterInterface;
use PDO;

class GAC {
    public ?CacheAdapterInterface $cacheAdapter = null;

    protected PDO $pdo;
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

    public function __construct(PDO $pdo, CacheAdapterInterface|array|null $cache = null) {
        $this->pdo = $pdo;
        $this->cachekey = 'gac';
        $this->cacheTtl = 1800;

        if ($cache instanceof CacheAdapterInterface) {
            $this->cacheAdapter = $cache;
        } elseif (is_array($cache)) {
            $dir = $cache['dir'] ?? __DIR__ . '/writable';
            $this->cacheAdapter = new \DancasDev\GAC\Drivers\CacheAdapter($dir);
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
        $this->scopePath = $scopePath;
        return $this;
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
     * Obtiene los permisos del usuario/cliente para el scope actual.
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
     * Obtiene las restricciones del usuario/cliente + globales.
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

    public function getPermissionList(?string $scope = null, bool $fromCache = true): array {
        $this->ensureLoaded($fromCache);
        $records = $this->entityCache['p'] ?? [];
        if ($scope === null) return $records;

        $result = [];
        foreach ($records as $module => $moduleRecords) {
            $best = $this->resolveScope($moduleRecords, $scope);
            if ($best !== null) {
                unset($best['s']);
                $result[$module] = $best;
            }
        }
        return $result;
    }

    public function getRestrictionList(?string $scope = null, bool $fromCache = true): array {
        $this->ensureLoaded($fromCache);
        $this->ensureGlobalRestrictions($fromCache);
        $records = $this->entityCache['r'] ?? [];

        if ($scope === null) {
            $data = $records;
            foreach ($this->globalRestrictions as $type => $rules) {
                if (!isset($data[$type])) $data[$type] = $rules;
            }
            return $data;
        }

        $data = [];
        foreach ($records as $type => $typeRecords) {
            $best = $this->resolveScope($typeRecords, $scope);
            if ($best !== null) $data[$type] = [$best];
        }
        foreach ($this->globalRestrictions as $type => $rules) {
            if (!isset($data[$type])) {
                $best = $this->resolveScope($rules, $scope);
                if ($best !== null) $data[$type] = [$best];
            }
        }
        return $data;
    }

    // ─── Cache ────────────────────────────────────────

    /**
     * Carga perezosa de permisos + restricciones de entidad.
     * Intenta cache primero, si falla ejecuta consultas y persiste.
     */
    protected function ensureLoaded(bool $fromCache): void {
        if ($this->entityLoaded) return;
        if (empty($this->entityType) || empty($this->entityId)) {
            throw new \Exception('Entity type and ID must be set before loading data.', 1);
        }

        if ($fromCache && $this->cacheAdapter) {
            $cached = $this->cacheAdapter->get($this->getCacheKey());
            if (is_array($cached) && isset($cached['p'])) {
                $this->entityCache = $cached;
                $this->entityLoaded = true;
                return;
            }
        }

        $this->entityCache['p'] = $this->getPermissionsFromDB();
        $this->entityCache['r'] = $this->getEntityRestrictionsFromDB();
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
            if (is_array($cached)) {
                $this->globalRestrictions = $cached;
                $this->globalLoaded = true;
                return;
            }
        }

        $this->globalRestrictions = $this->getGlobalRestrictionsFromDB();
        $this->globalLoaded = true;

        if ($this->cacheAdapter) {
            $this->cacheAdapter->save($this->getGlobalCacheKey(), $this->globalRestrictions, $this->cacheTtl);
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

    public function purgePermissionsBy(string $entityType, array $entityIds = []): bool {
        return $this->purgeCacheBy($entityType, $entityIds);
    }

    public function purgeRestrictionsBy(string $entityType, array $entityIds = []): bool {
        if ($entityType === 'global') {
            if (empty($this->cacheAdapter)) {
                return false;
            }
            $this->cacheAdapter->delete($this->getGlobalCacheKey());
            return true;
        }

        return $this->purgeCacheBy($entityType, $entityIds);
    }

    protected function purgeCacheBy(string $entityType, array $entityIds = []): bool {
        if (empty($this->cacheAdapter)) {
            return false;
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
     * Obtiene y procesa los permisos desde la base de datos.
     *
     * FLUJO DE LA GRANULARIDAD:
     *
     * 1. QUERY: Trae todos los registros de gac_module_permission para la entidad
     *    (permisos directos del usuario/cliente + permisos heredados de roles).
     *    ORDER BY from_entity_type DESC  → primero personales (type=1/2), luego roles (type=0).
     *
     * 2. CLASIFICACIÓN: Separa los registros según to_entity_type:
     *    - to_entity_type='0' (Categoría): se guarda category_id para expandir después
     *    - to_entity_type='1' (Módulo directo): se guarda module_id
     *
     * 3. PRIORIDAD: Asigna prioridad numérica a cada registro:
     *    - Personal (from_entity_type != '0') → priority = -1 (máxima)
     *    - Rol      (from_entity_type = '0')  → priority = del rol en gac_role_entity
     *
     * 4. QUERY MÓDULOS: Trae datos de gac_module + gac_module_category para
     *    resolver qué módulos pertenecen a cada categoría y viceversa.
     *
     * 5. ORDENAMIENTO: Ordena los permisos por prioridad ascendente.
     *    Así los permisos personales (-1) quedan antes que los de roles (0..N).
     *
     * 6. EXPANSIÓN + DEDUP: Itera los permisos en orden de prioridad:
     *    - Si el permiso es sobre una CATEGORÍA, lo expande a TODOS los módulos
     *      que pertenecen a esa categoría.
     *    - Si el permiso es sobre un MÓDULO, aplica solo a ese módulo.
     *    - Dedup por (module_code, scope_path): el primer permiso encontrado
     *      (mayor prioridad) para cada combinación es el que gana.
     *      Ej: si personal tiene users con scope="*" y rol también,
     *          el personal gana por tener priority=-1.
     *
     * RESULTADO: array[module_code][] = {s, i, d, f, l}
     *   - s: scope_path del permiso
     *   - i: id del permiso en gac_module_permission
     *   - d: is_developing del módulo
     *   - f: feature (bitmask)
     *   - l: level
     */
    protected function getPermissionsFromDB(): array {
        $response = [];

        if (empty($this->entityType) || $this->entityType === '0') {
            return $response;
        }

        $roleData = $this->getEntityRoleData();

        $query = 'SELECT id, from_entity_type, from_entity_id, to_entity_type, to_entity_id, scope_path, feature, level';
        $query .= ' FROM `gac_module_permission` WHERE ((`from_entity_type` = ? AND `from_entity_id` = ?)';
        foreach ($roleData['list'] as $key => $id) {
            $query .= ' OR (`from_entity_type` = \'0\' AND `from_entity_id` = ?)';
        }
        $query .= ') AND `deleted_at` IS NULL AND `is_disabled` = \'0\'';
        $query .= ' ORDER BY `from_entity_type` DESC';
        $stmt = $this->pdo->prepare($query);
        $stmt->execute(array_merge([$this->entityType, $this->entityId], $roleData['list']));
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($result) || empty($result)) {
            return $response;
        }

        $categoryIds = [];
        $moduleIds = [];
        $permissions = [];
        foreach ($result as $key => $record) {
            if ($record['to_entity_type'] == '0') {
                $categoryIds[$record['to_entity_id']] = $record['to_entity_id'];
            } else {
                $moduleIds[$record['to_entity_id']] = $record['to_entity_id'];
            }

            $record['feature'] = (int) ($record['feature'] ?? 0);
            $record['level'] = (int) $record['level'];
            if ($record['from_entity_type'] !== '0') {
                $record['priority'] = -1;
            } else {
                $record['priority'] = $roleData['priority'][$record['from_entity_id']] ?? 100;
            }

            $permissions[$key] = $record;
        }

        $modulesBy = ['category' => [], 'module' => []];
        $hasModules = !empty($moduleIds);
        $hasCategories = !empty($categoryIds);
        if ($hasModules || $hasCategories) {
            $query = 'SELECT a.id, a.module_category_id, a.code, a.is_developing FROM gac_module AS a INNER JOIN gac_module_category AS b ON a.module_category_id = b.id WHERE (';
            if ($hasCategories) {
                $query .= 'a.module_category_id IN (' . implode(',', $categoryIds) . ')';
            }
            if ($hasModules) {
                $query .= ' OR a.id IN (' . implode(',', $moduleIds) . ')';
            }
            $query .= ') AND a.deleted_at IS NULL AND b.deleted_at IS NULL AND a.is_disabled = \'0\' AND b.is_disabled = \'0\'';

            $stmt = $this->pdo->prepare($query);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($result as $record) {
                $modulesBy['category'][$record['module_category_id']][$record['id']] = $record['id'];
                $modulesBy['module'][$record['id']] = $record;
            }
        }

        if (!empty($roleData['priority'])) {
            usort($permissions, function(array $a, array $b) {
                return $a['priority'] <=> $b['priority'];
            });
        }

        // Expand to modules and dedup by (module_code, scope_path)
        $dedupMap = [];
        foreach ($permissions as $permission) {
            $moduleResult = [];
            if ($permission['to_entity_type'] === '0') {
                $moduleResult = $modulesBy['category'][$permission['to_entity_id']] ?? [];
            } elseif ($permission['to_entity_type'] === '1') {
                $moduleResult[] = $permission['to_entity_id'];
            }

            foreach ($moduleResult as $moduleId) {
                $moduleData = $modulesBy['module'][$moduleId] ?? null;
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
                        'l' => $permission['level']
                    ];
                }
            }
        }

        return $response;
    }

    /**
     * Obtiene y procesa las restricciones de la entidad desde la base de datos.
     *
     * FLUJO DE LA GRANULARIDAD:
     *
     * 1. QUERY: Trae todas las restricciones de la entidad (directas + heredadas de roles).
     *    NO incluye globales (entity_type='3') — esas se cargan aparte en getGlobalRestrictionsFromDB().
     *    ORDER BY entity_type DESC → primero personales (type=1/2), luego roles (type=0).
     *
     * 2. PRIORIDAD: Asigna prioridad numérica a cada registro:
     *    - Personal (entity_type coincide con la entidad actual) → priority = -1 (máxima)
     *    - Rol      (entity_type='0') → priority = prioridad del rol en gac_role_entity
     *
     * 3. ORDENAMIENTO: Ordena por prioridad ascendente.
     *    Personales (-1) primero, luego roles por su prioridad (0..N).
     *
     * 4. DEDUP POR TIPO (TYPE): La primera entidad que establece una restricción
     *    para un TYPE gana, y las siguientes entidades con el mismo TYPE se descartan.
     *    Ej: si el usuario tiene restricción 'date' (personal, priority=-1)
     *        y un rol también tiene 'date' (priority=0), solo la personal se conserva.
     *    Esto permite que un usuario SOBREESCRIBA las restricciones de su rol
     *    para un TYPE específico, sin perder los otros tipos del rol.
     *
     * RESULTADO: array[type][] = {s, i, r, d}
     *   - s: scope_path
     *   - i: id de la restricción
     *   - r: rule (in_range, before, allow, deny, etc.)
     *   - c: config decodificada del JSON config
     */
    protected function getEntityRestrictionsFromDB(): array {
        $roleData = $this->getEntityRoleData();

        $query = 'SELECT id, entity_type, entity_id, scope_path, type, rule, `config`';
        $query .= ' FROM `gac_restriction`';
        $query .= ' WHERE `deleted_at` IS NULL AND `is_disabled` = \'0\'';
        $query .= ' AND ((`entity_type` = ? AND `entity_id` = ?)';
        foreach ($roleData['list'] as $id) {
            $query .= ' OR (`entity_type` = \'0\' AND `entity_id` = ?)';
        }
        $query .= ')';
        $query .= ' ORDER BY `entity_type` DESC';

        $params = array_merge([$this->entityType, $this->entityId], $roleData['list']);
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($result) || empty($result)) {
            return [];
        }

        // Asignar prioridad
        $records = [];
        $entityTypeKey = (string) $this->entityType;
        foreach ($result as $record) {
            if ($record['entity_type'] === $entityTypeKey) {
                $priority = -1;
            } else {
                $priority = $roleData['priority'][$record['entity_id']] ?? 100;
            }

            $records[] = [
                'id' => $record['id'],
                'priority' => $priority,
                'entity_type' => $record['entity_type'],
                'entity_id' => $record['entity_id'],
                'scope_path' => $record['scope_path'] ?? '*',
                'type' => $record['type'],
                'r' => $record['rule'],
                'config' => @json_decode($record['config'], true) ?? []
            ];
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
        $query = 'SELECT id, entity_type, entity_id, scope_path, type, rule, `config`';
        $query .= ' FROM `gac_restriction`';
        $query .= ' WHERE `deleted_at` IS NULL AND `is_disabled` = \'0\'';
        $query .= ' AND `entity_type` = \'3\'';

        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($result) || empty($result)) {
            return [];
        }

        $response = [];
        foreach ($result as $record) {
            $response[$record['type']][] = [
                's' => $record['scope_path'] ?? '*',
                'i' => $record['id'],
                'r' => $record['rule'],
                'c' => @json_decode($record['config'], true) ?? []
            ];
        }

        return $response;
    }

    protected function getEntitiesByRoleIds(array $roleIds): array {
        if (empty($roleIds)) {
            return [];
        }

        $roleIds = array_map('intval', $roleIds);
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $query = 'SELECT id, role_id, entity_type, entity_id FROM `gac_role_entity` WHERE role_id IN (' . $placeholders . ') AND is_disabled = \'0\' AND deleted_at IS NULL';
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($roleIds);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function getEntityRoleData(bool $reset = false) {
        if ($reset || empty($this->entityRoleData)) {
            $data = ['list' => [], 'priority' => []];
            $query = 'SELECT b.id, b.code, a.priority';
            $query .= ' FROM `gac_role_entity` AS a INNER JOIN `gac_role` AS b ON a.role_id = b.id';
            $query .= ' WHERE a.entity_type = :entity_type AND a.entity_id = :entity_id AND a.is_disabled = \'0\' AND b.is_disabled = \'0\' AND a.deleted_at IS NULL AND b.deleted_at IS NULL';
            $query .= ' ORDER BY a.priority ASC';
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':entity_type', $this->entityType, PDO::PARAM_STR);
            $stmt->bindParam(':entity_id', $this->entityId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($result as $role) {
                $data['priority'][$role['id']] = (int) $role['priority'];
                $data['list'][] = $role['id'];
            }

            $this->entityRoleData = $data;
        }

        return $this->entityRoleData;
    }

    // ─── Utilidades ────────────────────────────────────────

    /**
     * Dado un conjunto de registros con distintos scopes (campo 's'),
     * elige cuál aplica para el scopePath actual ($this->scopePath).
     *
     * REGLAS DE RESOLUCIÓN (en orden de precedencia):
     *
     * 1. MATCH EXACTO — Si algún registro tiene scope_path igual al scope actual,
     *    ese gana sin importar qué más exista.
     *
     * 2. WILDCARD "prefijo/*" — Si existe un registro con scope_path "X/*" y el
     *    scope actual está bajo X (es X exacto o cualquier sub-ruta), aplica.
     *    Si hay múltiples wildcards que matchean, gana el de mayor profundidad
     *    (el más específico).
     *    Ej: "empresaX/*" cubre "empresaX", "empresaX/SucursalA", "empresaX/SucursalA/Depto"
     *        "empresaX/SucursalA/*" es más específico y gana sobre "empresaX/*"
     *
     * 3. FALLBACK "*" — Si ningún registro matchea exacto ni por wildcard,
     *    se usa el registro con scope "*" (global). Si no existe, retorna null.
     *
     * EJEMPLOS con scopePath = "empresaX/SucursalA":
     *
     *   Registros disponibles: [{s:"*"}, {s:"empresaX"}, {s:"empresaX/SucursalA"}]
     *   → Match exacto "empresaX/SucursalA" → gana ese
     *
     *   Registros disponibles: [{s:"*"}, {s:"empresaX"}, {s:"empresaX/*"}]
     *   → No hay match exacto. Wildcard "empresaX/*" matchea → gana ese
     *   → "empresaX" sin /* NO hereda (no matchea)
     *
     *   Registros disponibles: [{s:"*"}, {s:"otraEmpresa/*"}]
     *   → Solo "*" matchea → gana "*"
     *
     *   Registros disponibles: [{s:"empresaX"}, {s:"empresaX/*"}, {s:"empresaX/SucursalA/*"}]
     *   → Wildcard más profundo "empresaX/SucursalA/*" gana sobre "empresaX/*"
     *
     * @param string|null $scopePath  Scope a resolver. null = usa $this->scopePath.
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
                // ¿El scope actual está bajo este prefijo?
                if ($current === $prefix || str_starts_with($current . '/', $prefix . '/')) {
                    if (!isset($wildcards[$depth])) {
                        $wildcards[$depth] = $rec;
                    }
                }
            }
        }

        if ($exact) return $exact;

        // Wildcard más profundo (más específico) primero
        if (!empty($wildcards)) {
            krsort($wildcards);
            return reset($wildcards);
        }

        return $global;
    }
}
