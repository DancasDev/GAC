<?php

namespace DancasDev\GAC;

use DancasDev\GAC\Permissions\Permissions;
use DancasDev\GAC\Restrictions\Restrictions;
use DancasDev\GAC\Adapters\DatabaseAdapter;
use DancasDev\GAC\Adapters\CacheAdapter;
use DancasDev\GAC\Exceptions\DatabaseAdapterException;
use DancasDev\GAC\Exceptions\CacheAdapterException;
use PDO;

class GAC {
    public $databaseAdapter;
    public $cacheAdapter;

    protected array $entityTypeKeys = ['user' => '1', 'client' => '2'];
    protected array $entityRoleData = []; // ['list' => [], 'priority' => []]
    protected $entityType;
    protected $entityId;

    protected $cacheTtl;
    protected $cachekey;

    public function setEntity(string $entityType, string|int $entityId) : GAC {
        $this ->entityType = (string) ($this ->entityTypeKeys[$entityType] ?? $entityType);
        $this ->entityId = $entityId;
        return $this;
    }

    public function setCacheTtl(int $ttl) : GAC {
        $this ->cacheTtl = $ttl;
        return $this;
    }

    public function setCacheKey(string $prefix) : GAC {
        $this ->cachekey = $prefix;
        return $this;
    }

    public function getEntityType() : string {
        return $this ->entityType;
    }

    public function getEntityId() : string|int {
        return $this ->entityId;
    }

    public function getCacheKey(string $type) : string {
        if ($type == 'restrictions_global') {
            return $this ->cachekey . '_r_global';
        }
        else {
            $type = substr($type, 0, 1);
            return $this ->cachekey . '_' . $type . '_' . $this ->entityType . '_' . $this ->entityId;
        }
    }

    public function getEntityRoleData(bool $reset = false) {
        if ($reset || empty($this ->entityRoleData)) {
            $data = ['list' => [], 'priority' => []];
            $result = $this ->databaseAdapter ->getRoles($this ->entityType, $this ->entityId);
            foreach ($result as $key => $role) {
                $data['priority'][$role['id']] = (int) $role['priority'];
                $data['list'][] = $role['id'];
            }

            $this ->entityRoleData = $data;
        }
        
        return $this ->entityRoleData;
    }

    /**
     * Establecer conexión a la base de datos
     * 
     * @param PDO|array $params - Parámetros de conexión a la base de datos
     * 
     * @throws DatabaseAdapterException
     * 
     * @return GAC
     */
    public function setDatabase($params) : GAC {
        if (is_array($params) || $params instanceof PDO) {
            $this ->databaseAdapter = new DatabaseAdapter($params);
        }
        elseif (is_object($params)) {
            if (!in_array('DancasDev\\GAC\\Adapters\\DatabaseAdapterInterface', class_implements($params))) {
                throw new DatabaseAdapterException('Invalid implementation: The database adapter must implement DatabaseAdapterInterface.', 1);
            }

            $this ->databaseAdapter = $params;
        }
        else {
            throw new DatabaseAdapterException('Need to provide database adapter.', 1);
        }

        return $this;
    }
    
    /**
     * Establecer cache
     * 
     * @param string|null $key (opcional) - Prefijo para la cache
     * @param string|int|null $ttl - (opcional) Tiempo de vida de la cache (en segundos)
     * @param string|object $dir - (opcional) Directorio donde se almacenará la cache o adaptador de cache
     * 
     * @throws CacheAdapterException
     * 
     * @return GAC
     */
    public function setCache(string|null $key = null, string|int|null $ttl = null, string|object $dir = null) : GAC {
        $this ->cachekey = $key ?? 'gac';
        $this ->cacheTtl = (int) ($ttl ?? 1800); // 30 minutos por defecto
        $dir ??= __DIR__ . '/writable';

        if(is_string($dir)) {
            $this ->cacheAdapter = new CacheAdapter($dir);
        }
        else {
            $classImplementList = class_implements($dir);
            if (!in_array('DancasDev\\GAC\\Adapters\\CacheAdapterInterface', $classImplementList)) {
                throw new CacheAdapterException('Invalid implementation: The cache adapter must implement CacheAdapterInterface.', 1);
            }
            
            $this ->cacheAdapter = $dir;
        }

        return $this;
    }

    /**
     * Obtener permisos a modulos
     * 
     * @param bool $fromCache - (Opcional) Indica si se obtienen los permisos desde la caché
     * 
     * @return Permissions
     */
    public function getPermissions(bool $fromCache = true) : Permissions {
        $type = 'permissions';
        if (empty($this ->entityType) || empty($this ->entityId)) {
            throw new \Exception('Entity type and ID must be set before loading data.', 1);
        }

        $permissions = null;
        if ($fromCache) {
            $permissions = $this ->getFromCache($type);
        }

        if (!is_array($permissions)) {
            $permissions = $this ->getPermissionsFromDB();
            $this ->saveToCache($type, $permissions);
        }

        return new Permissions($permissions);
    }

    /**
     * Obtener restricciones del usuario
     * 
     * @param bool $fromCache - (Opcional) Indica si se obtienen las restricciones desde la caché
     * 
     * @return Restrictions
     */
    public function getRestrictions(bool $fromCache = true) : Restrictions {
        if (empty($this ->entityType) || empty($this ->entityId)) {
            throw new \Exception('Entity type and ID must be set before loading data.', 1);
        }

        // Cache
        $restrictionsP = null;
        $restrictionsG = null;
        if ($fromCache) {
            $restrictionsG = $this ->getFromCache('restrictions_global');
            $restrictionsP = $this ->getFromCache('restrictions');
        }

        // Base de datos
        if (!is_array($restrictionsG)) {
            $restrictionsG = $this ->getRestrictionsFromDB(true);
            $this ->saveToCache('restrictions_global', $restrictionsG);
        }
        if (!is_array($restrictionsP)) {
            $restrictionsP = $this ->getRestrictionsFromDB(false);
            $this ->saveToCache('restrictions', $restrictionsP);
        }

        // Formatear
        $restrictions = [];
        foreach ($restrictionsG as $restriction) {
            $restrictions[$restriction['category_code']][$restriction['type_code']]['g'] = $restriction;
        }
        foreach ($restrictionsP as $restriction) {
            $restrictions[$restriction['category_code']][$restriction['type_code']]['p'] = $restriction;
        }

        return new Restrictions($restrictions);
    }

    /**
     * Limpiar cache 
     * 
     * @param bool $includeGlobal - Indica si se eliminan también las restricciones globales
     * 
     * @throws CacheAdapterException
     * 
     * @return bool TRUE en caso de éxito, FALSE en caso de fallo.
     */
    public function clearCache(bool $includeGlobal = false) : bool {
        if (empty($this ->cacheAdapter)) {
            throw new CacheAdapterException('Cache adapter not set.', 1);
        }

        # Permisos
        $cacheKey = $this ->getCacheKey('permissions');
        $this ->cacheAdapter ->delete($cacheKey);

        # Restricciones
        $cacheKey = $this ->getCacheKey('restrictions');
        $this ->cacheAdapter ->delete($cacheKey);

        // globales
        if ($includeGlobal) {
            $cacheKey = $this ->getCacheKey('restrictions_global');
            $this ->cacheAdapter ->delete($cacheKey);
        }       

        return true;
    }
    
    /**
     * Purga los permisos almacenados en caché según ciertos criterios.
     * 
     * @param string $entityType - Entidad (user, client, role, global).
     * @param array $entityIds - IDs de las entidades.
     * 
     * @return bool
     */
    public function purgePermissionsBy(string $entityType, array $entityIds = []) : bool {
        return $this ->purgeCacheBy('permissions', $entityType, $entityIds);
    }
    
    /**
     * Purga las restricciones almacenadas en caché según ciertos criterios.
     * 
     * @param string $entityType - Entidad (user, client, role, global).
     * @param array $entityIds - IDs de las entidades.
     * 
     * @return bool
     */
    public function purgeRestrictionsBy(string $entityType, array $entityIds = []) : bool {
        return $this ->purgeCacheBy('restrictions', $entityType, $entityIds);
    }

    /**
     * Purga los datos almacenados en caché según ciertos criterios.
     * 
     * @param string $type - Tipo de datos (permissions, restrictions).
     * @param string $entityType - Entidad (user, client, role, global).
     * @param array $entityIds - IDs de las entidades.
     * 
     * @return bool
     */
    protected function purgeCacheBy(string $type, string $entityType, array $entityIds = []) : bool {
        if (empty($this ->cacheAdapter)) {
            throw new CacheAdapterException('Cache adapter not set.', 1);
        }

        $type = substr($type, 0, 1);
        if ($entityType == 'global') {
            $this ->cacheAdapter ->deleteMatching($this ->cachekey . '_' . $type . '_*');
        }
        else {
            if (empty($entityIds)) {
                return false;
            }

            # Obtener lista de la entidades a purgar
            $list = []; // (user/client -> IDs)
            if ($entityType == 'user') {
                $list['1'] = $entityIds;
            }
            elseif ($entityType == 'client') {
                $list['2'] = $entityIds;
            }
            elseif($entityType == 'role') {
                $result = $this ->databaseAdapter ->getEntitiesByRoles($entityIds);
                foreach ($result as $record) {
                    $list[$record['entity_type']] ??= [];
                    $list[$record['entity_type']][$record['entity_id']] = $record['entity_id'];
                }
            }
            
            # Purga listas
            foreach ($list as $entityKey => $subList) {
                foreach ($subList as $entityId) {
                    $entityId = (string) $entityId;
                    $this ->cacheAdapter ->delete($this ->cachekey . '_' . $type . '_' . $entityKey . '_' . $sub);
                }
            }
        }

        return true;
    }

    /**
     * Obtener registros desde la cache
     * 
     * @param string $type - tipo de carga ['permissions','restrictions']
     * 
     * 
     * @throws CacheAdapterException
     * 
     * @return array|null null en caso de que no exista nada almacenado en cache
     */
    protected function getFromCache(string $type) : array|null {
        $response = null;
        
        if (empty($this ->cacheAdapter)) {
            throw new CacheAdapterException('Cache adapter not set.', 1);
        }
        
        $cacheKey = $this ->getCacheKey($type);
        $data = $this ->cacheAdapter ->get($cacheKey);
        if (is_array($data)) {
            $response = $data;
        }

        return $response;
    }

    /**
     * Almacenar registros en la cache
     * 
     * @param string $type - tipo de carga ['permissions','restrictions']
     * @param array $data - Datos a almacenar
     * 
     * @throws CacheAdapterException
     * 
     * @return bool TRUE en caso de éxito, FALSE en caso de fallo. 
     */
    protected function saveToCache(string $type, array $data) : bool {
        if (empty($this ->cacheAdapter)) {
            throw new CacheAdapterException('Cache adapter not set.', 1);
        }

        $cacheKey = $this ->getCacheKey($type);
        return $this ->cacheAdapter ->save($cacheKey, $data, $this ->cacheTtl);
    }
    
    /**
     * Consultar y depurar los permisos de una entidad (y sus roles)
     *
     * @return array Listado de permisos
     */
    protected function getPermissionsFromDB() : array { 
        $response = [];

        if (empty($this ->databaseAdapter)) {
            throw new DatabaseAdapterException('Database adapter not set.', 1);
        }

        # Obtener datos
        // Roles
        $roleData = $this ->getEntityRoleData();
        // permisos relacionados a la entidad y los roles asignados al mismo
        $result = $this ->databaseAdapter ->getPermissions($this ->entityType, $this ->entityId, $roleData['list']);
        if (!is_array($result) || empty($result)) {
            return $response;
        }

        # Depurar datos
        $categoryIds = [];
        $moduleIds = [];
        $permissions = [];
        foreach ($result as $key => $record) {
            // Almacenar Módulo o Categoría
            if ($record['to_entity_type'] == '0') {
                $categoryIds[$record['to_entity_id']] = $record['to_entity_id'];
            }
            else {
                $moduleIds[$record['to_entity_id']] = $record['to_entity_id'];
            }

            // Formatear permiso
            $record['feature'] = !empty($record['feature']) ? explode(',', $record['feature']) : [];
            $record['level'] = (int) $record['level'];
            if ($record['from_entity_type'] !== '0') {
                $record['priority'] = -1; // permisos personales primero
            }
            else {
                $record['priority'] = $roleData['priority'][$record['from_entity_id']] ?? 100; // permisos heredados despues
            }

            // Almacenar permiso
            $permissions[$key] = $record;
        }

        // Datos de los modulos
        $modulesBy = ['category' => [], 'module' => []];
        $result = $this ->databaseAdapter ->getModulesData($categoryIds, $moduleIds);
        foreach ($result as $record) {
            $modulesBy['category'][$record['module_category_id']][$record['id']] = $record['id']; // solo referencial
            $modulesBy['module'][$record['id']] = $record; // modulo con todos los datos
        }

        
        // ordenar permisos por prioridad 
        if (!empty($roleData['priority'])) {
            usort($permissions, function(array $a, array $b) {
                return $a['priority'] <=> $b['priority'];
            });
        }

        # Ejecutar granularidad de permisos
        foreach ($permissions as $permission) {
            $result = [];
            // Acceso por categoría
            if ($permission['to_entity_type'] === '0') {
                $result = $modulesBy['category'][$permission['to_entity_id']] ?? [];
            }
            // Acceso por módulo
            elseif ($permission['to_entity_type'] === '1') {
                $result[] = $permission['to_entity_id']; 
            }
            
            // Almacenar minetras...
            foreach ($result as $moduleId) {
                $moduleData = $modulesBy['module'][$moduleId] ?? null;
                // sea un modulo existente
                if ($moduleData === null) {
                    continue;
                }
                // el permiso al modulo no se almaceno anteriormente (aqui es donde entra en juego el orden por medio del atribuyo "priority")
                elseif (!array_key_exists($moduleData['code'], $response)) {
                    $response[$moduleData['code']] = [
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
     * Consultar restricciones de la entidad (y sus roles) o globales
     * 
     * @param bool $isGlobal - Indica si se obtienen restricciones globales
     * 
     * @return array Listado de restricciones
     */
    protected function getRestrictionsFromDB(bool $isGlobal = false) : array {
        $response = [];

        if (empty($this ->databaseAdapter)) {
            throw new DatabaseAdapterException('Database adapter not set.', 1);
        }
        # Obtener datos
        $entityType = '3'; // tipo de entidad global
        $entityId = '0'; // id irrelevante
        $roleIds = [];
        if (!$isGlobal) {
            $entityType = $this ->entityType;
            $entityId = $this ->entityId;
            $result = $this ->getEntityRoleData();
            $roleIds = $result['list'];
        }

        $result = $this ->databaseAdapter ->getRestrictions($entityType, $entityId, $roleIds);
        if (!is_array($result) || empty($result)) {
            return $response;
        }

        # Depurar datos
        if (!$isGlobal) {
            foreach ($result as $key => $record) {
                // Asignar prioridad
                if ($record['entity_type'] === $this ->entityType) {
                    $record['priority'] = -1; // personales primero
                }
                else {
                    $record['priority'] = $roleData['priority'][$record['entity_id']] ?? 100; // heredadas de roles despues
                }

                // Formatear y almacenar
                $record['data'] = @json_decode($record['data'], true) ?? [];
                $response[] = [
                    'id' => $record['id'],
                    'priority' => $record['priority'],
                    'entity_type' => $record['entity_type'],
                    'entity_id' => $record['entity_id'],
                    'category_code' => $record['category_code'],
                    'type_code' => $record['type_code'],
                    'data' => $record['data']
                ];
            }
            
            // ordenar por prioridad 
            if (!empty($roleData['priority'])) {
                usort($response, function(array $a, array $b) {
                    return $a['priority'] <=> $b['priority'];
                });
            }

            // Descartar duplicados por granularidad
            $result = [];
            $reservationList  = []; // una sola reserva por categoría
            foreach ($response as $restriction) {
                $entityKey = $restriction['entity_type'] . '_' . $restriction['entity_id'];
                $reservationList[$restriction['category_code']] ??= $entityKey; // asignar reserva si no existe
                // si ya existe una reserva y no es la misma, descartar
                if ($reservationList[$restriction['category_code']] !== $entityKey) {
                    continue;
                }

                // Almacenar restricción
                unset($restriction['priority']); // ya no es necesario
                $result[] = $restriction;
            }

            // Almacenar
            $response = $result;
        }
        else {
            foreach ($result as $key => $record) {
                $record['data'] = @json_decode($record['data'], true) ?? [];
                $response[] = [
                    'id' => $record['id'],
                    'entity_type' => $record['entity_type'],
                    'entity_id' => $record['entity_id'],
                    'category_code' => $record['category_code'],
                    'type_code' => $record['type_code'],
                    'data' => $record['data']
                ];
            }
        }
        
        return $response;
    }
}