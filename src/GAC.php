<?php

namespace DancasDev\GAC;

use DancasDev\GAC\Permission\Permission;
use DancasDev\GAC\Adapters\DatabaseAdapter;
use DancasDev\GAC\Adapters\CacheAdapter;
use DancasDev\GAC\Exceptions\DatabaseAdapterException;
use DancasDev\GAC\Exceptions\CacheAdapterException;
use PDO;

class GAC {
    public $databaseAdapter;
    public $cacheAdapter;

    protected array $permissions = [];

    protected array $entityTypeKeys = ['user' => '1', 'client' => '2'];
    protected $entityType;
    protected $entityId;

    protected $cacheTtl;
    protected $cachePermissionsPrefix;

    public function setEntity(string $entityType, string|int $entityId) : GAC {
        $this ->entityType = (string) ($this ->entityTypeKeys[$entityType] ?? $entityType);
        $this ->entityId = $entityId;
        return $this;
    }

    public function setCacheTtl(int $ttl) : GAC {
        $this ->cacheTtl = $ttl;
        return $this;
    }

    public function setCachePermissionsPrefix(string $prefix) : GAC {
        $this ->cachePermissionsPrefix = $prefix;
        return $this;
    }

    public function getEntityType() : string {
        return $this ->entityType;
    }

    public function getEntityId() : string|int {
        return $this ->entityId;
    }

    public function getPermissions() : array {
        return $this ->permissions ?? [];
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
    public function setDatabaseAdapter($params) : GAC {
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
     * @param string|null $permissionsPrefix (opcional) - Prefijo para la cache
     * @param string|int|null $ttl - (opcional) Tiempo de vida de la cache (en segundos)
     * @param string $dir - (opcional) Directorio donde se almacenará la cache o adaptador de cache
     * 
     * @throws CacheAdapterException
     * 
     * @return GAC
     */
    public function setCache(string|null $permissionsPrefix = null, string|int|null $ttl = null, string|object $dir = null) : GAC {
        $this ->cachePermissionsPrefix = $permissionsPrefix ?? 'permissions_';
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

    /*Permisos*/
    /**
     * Cargar los permisos de una entidad
     *  
     * @param bool $fromCache - (Opcional) Indica si se obtienen los permisos desde la caché
     * @param bool $onlyEnabled - (Opcional) Indica si se obtienen solo los permisos habilitados
     * 
     * @return GAC
     */
    public function loadPermissions(bool $fromCache = true, bool $onlyEnabled = true) {
        if (empty($this ->entityType) || empty($this ->entityId)) {
            $this ->permissions = [];
            return $this;
        }

        $permissions = [];

        if ($fromCache) {
            $permissions = $this ->getPermissionsFromCache($this ->entityType, $this ->entityId, $onlyEnabled);
        }

        if (empty($permissions)) {
            $permissions = $this ->getPermissionsFromDB($this ->entityType, $this ->entityId, $onlyEnabled);
        }

        if ($fromCache) {
            $this ->cacheAdapter ->save($this ->cachePermissionsPrefix . $this ->entityType . '_' . $this ->entityId, [
                'onlyEnabled' => $onlyEnabled,
                'permissions' => $permissions
            ], $this ->cacheTtl);
        }

        $this ->permissions = $permissions;

        return $this;
    }

    /**
     * Verificar si la entidad tiene permiso para acceder a un módulo
     * 
     * @param string $moduleCode - Código del módulo
     * 
     * @return bool
     */
    public function hasPermission(string $moduleCode) : bool {
        return array_key_exists($moduleCode, $this ->permissions);
    }

    /**
     * Obtener permiso de la entidad
     * 
     * @param string $moduleCode - Código del módulo
     * 
     * @return Permission|null Instancia de Permission con los datos del permiso, NULL si no tiene permiso
     */
    public function getPermission(string $moduleCode) : Permission|null {
        if (!$this ->hasPermission($moduleCode)) {
            return null;
        }

        return new Permission(array_merge($this ->permissions[$moduleCode],['module_code' => $moduleCode]));   
    }

    /**
     * Obtener los permisos de una entidad desde la caché
     * 
     * @param string $entityType - Tipo de entidad
     * @param string|int $entityId - ID de la entidad
     * @param bool $onlyEnabled - (Opcional) Indica si se obtienen solo los permisos habilitados
     * 
     * @throws CacheAdapterException
     * 
     * @return array Listado de permisos
     */
    protected function getPermissionsFromCache(string $entityType, string|int $entityId, bool $onlyEnabled = true) : array {
        $response = [];
        
        if (empty($this ->cacheAdapter)) {
            throw new CacheAdapterException('Cache adapter not set.', 1);
        }
        
        $key = $this ->cachePermissionsPrefix . $entityType . '_' . $entityId;
        
        $data = $this ->cacheAdapter ->get($key);
        if (!empty($data)) {
            if ($data['onlyEnabled'] === $onlyEnabled) {
                $response = $data['permissions'];
            }
            else {
                $this ->cacheAdapter ->delete($key);
            }
        }

        return $response;
    }

    /**
     * Consultar y depurar los permisos de una entidad (y sus roles)
     * 
     * @param string $entityType - Tipo de entidad
     * @param string|int $entityId - ID de la entidad
     * @param bool $onlyEnabled - (Opcional) Indica si se obtienen solo los permisos habilitados
     * 
     * @return array Listado de permisos
     */
    protected function getPermissionsFromDB(string $entityType, string|int $entityId, bool $onlyEnabled = true) : array {
        $response = [];

        if (empty($this ->databaseAdapter)) {
            throw new DatabaseAdapterException('Database adapter not set.', 1);
        }

        # Consultar los roles asignados a la entidad
        $rolePriority = [];
        $roleIds = [];
        $result = $this ->databaseAdapter ->getRoles($entityType, $entityId, $onlyEnabled);
        foreach ($result as $key => $role) {
            $rolePriority[$role['id']] = (int) $role['priority'];
            $roleIds[] = $role['id'];
        }

        # Consultar permisos relacionados al usuario y los roles asignados al mismo
        $permissions = $this ->databaseAdapter ->getPermissions($entityType, $entityId, $roleIds, $onlyEnabled);
        if (empty($permissions)) {
            return $response;
        }

        # Depurar permisos
        $modulesAndCategories = ['category' => [], 'module' => []];
        foreach($permissions as $key => $permission) {
            // Formatear
            $permissions[$key]['feature'] = !empty($permission['feature']) ? explode(',', $permission['feature']) : [];
            $permissions[$key]['level'] = (int) $permission['level'];
            if ($permission['from_entity_type'] === '0') {
                $permissions[$key]['priority'] = $rolePriority[$permission['from_entity_id']] ?? 100;
            }
            else {
                $permissions[$key]['priority'] = -1;
            }

            // Almacenar
            if ($permission['to_entity_type'] == '0') {
                $modulesAndCategories['category'][$permission['to_entity_id']] = $permission['to_entity_id'];
            }
            else {
                $modulesAndCategories['module'][$permission['to_entity_id']] = $permission['to_entity_id'];
            }
        }

        if (!empty($rolePriority)) {
            usort($permissions, function(array $a, array $b) {
                return $a['priority'] <=> $b['priority']; // ordenar por prioridad
            });
        }

        // Granularidad de permisos (categoria -> módulo)
        $result = $this ->databaseAdapter ->getModulesAndCategories($modulesAndCategories['category'], $modulesAndCategories['module']);
        $modulesAndCategories = ['category' => [], 'module' => []];
        foreach ($result as $values) {
            $modulesAndCategories['category'][$values['module_category_id']][$values['id']] = $values['id'];
            $modulesAndCategories['module'][$values['id']] = $values;
        }

        foreach ($permissions as $permission) {
            $result = [];
            if ($permission['to_entity_type'] === '0') {
                $result = $modulesAndCategories['category'][$permission['to_entity_id']] ?? []; // Acceso por categoría
            }
            elseif ($permission['to_entity_type'] === '1') {
                $result[] = $permission['to_entity_id']; // Acceso por módulo
            }

            foreach ($result as $moduleId) {
                $moduleData = $modulesAndCategories['module'][$moduleId] ?? null;
                if ($moduleData === null) {
                    continue;
                }
                elseif (!array_key_exists($moduleData['code'], $response)) {
                    $response[$moduleData['code']] = [
                        'id' => $permission['id'],
                        'module_id' => $moduleData['id'],
                        'module_is_developing' => $moduleData['is_developing'],
                        'feature' => $permission['feature'],
                        'level' => $permission['level'],
                        'is_disabled' => $permission['is_disabled']
                    ];
                }
            }
        }

        return $response;
    }

    /*Restricciones*/
}