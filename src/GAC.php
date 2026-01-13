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
    protected array $entityRoleData = []; // ['list' => [], 'priority' => []]
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

    public function getCachePermissionsKey() : string {
        return $this ->cachePermissionsPrefix . '_' . $this ->entityType . '_' . $this ->entityId;
    }

    public function getEntityRoleData(bool $reset = false) {
        if ($reset || empty($this ->entityRoleData)) {
            $data = ['list' => [], 'priority' => []];
            $result = $this ->databaseAdapter ->getRoles($entityType, $entityId);
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
        $this ->cachePermissionsPrefix = $permissionsPrefix ?? 'permissions';
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
     * 
     * @return GAC
     */
    public function loadPermissions(bool $fromCache = true) {
        if (empty($this ->entityType) || empty($this ->entityId)) {
            $this ->permissions = [];
            return $this;
        }

        $permissions = null;

        if ($fromCache) {
            $permissions = $this ->getPermissionsFromCache();
        }

        if (!is_array($permissions)) {
            $permissions = $this ->getPermissionsFromDB();
            
            // Almacenar resultado
            $cacheKey = $this ->getCachePermissionsKey();
            $this ->cacheAdapter ->save($cacheKey, $permissions, $this ->cacheTtl);
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
     * @throws CacheAdapterException
     * 
     * @return array|null Listado de permisos, null en caso de que no exista nada almacenado en cache
     */
    protected function getPermissionsFromCache() : array|null {
        $response = null;
        
        if (empty($this ->cacheAdapter)) {
            throw new CacheAdapterException('Cache adapter not set.', 1);
        }
        
        $cacheKey = $this ->getCachePermissionsKey();
        $data = $this ->cacheAdapter ->get($cacheKey);
        if (is_array($data)) {
            $response = $data;
        }

        return $response;
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
        $result = $this ->databaseAdapter ->getPermissions($entityType, $entityId, $roleData['list']);
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
            $modulesBy['category'][$values['module_category_id']][$values['id']] = $values['id']; // solo referencial
            $modulesBy['module'][$values['id']] = $values; // modulo con todos los datos
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
                        'id' => $permission['id'],
                        'module_id' => $moduleData['id'],
                        'module_is_developing' => $moduleData['is_developing'],
                        'feature' => $permission['feature'],
                        'level' => $permission['level']
                    ];
                }
            }
        }

        return $response;
    }

    /*Restricciones*/
}