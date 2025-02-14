<?php

namespace DancasDev\GAC;

use DancasDev\GAC\Permission\Permission;
use DancasDev\GAC\Adapters\DatabaseAdapter;
use DancasDev\GAC\Adapters\CacheAdapter;
use DancasDev\GAC\Exceptions\DatabaseAdapterException;
use DancasDev\GAC\Exceptions\CacheAdapterException;


class GAC {
    public $databaseAdapter;
    public $cacheAdapter;

    protected array $permissions = [];

    protected array $entityTypeKeys = ['user' => '1', 'token_external' => '2'];
    protected $entityType;
    protected $entityId;

    protected int $cacheTtl = 1800;
    protected string $cachePermissionsPrefix = 'permissions_';
    
    public function __construct($databaseData, $cacheDir = null) {
        // Validar conexión a base de datos
        $this ->setDatabaseAdapter($databaseData);
        

        // Validar almacenamiento en caché
        $this ->setCacheAdapter($cacheDir);
    }

    public function setDatabaseAdapter($databaseAdapter) : GAC {
        if (is_object($databaseAdapter)) {
            if (!in_array('DancasDev\\GAC\\Adapters\\DatabaseAdapterInterface', class_implements($databaseAdapter))) {
                throw new DatabaseAdapterException('Invalid implementation: The database adapter must implement DatabaseAdapterInterface.', 1);
            }
        }
        elseif (is_array($databaseAdapter)) {
            $valid = true;
            foreach (['host', 'username', 'password', 'database'] as $key) {
                if (!array_key_exists($key, $databaseAdapter) || !is_string($databaseAdapter[$key])) {
                    $valid = false;
                    break;
                }
            }

            if ($valid) {
                $this ->databaseAdapter = new DatabaseAdapter($databaseAdapter['host'], $databaseAdapter['username'], $databaseAdapter['password'], $databaseAdapter['database']);
            }
            else {
                throw new DatabaseAdapterException('Invalid connection parameters: you need to correctly provide the following parameters: host, username, password and database.', 1);
            }
        }
        else {
            throw new DatabaseAdapterException('Need to provide database adapter.', 1);
        }

        return $this;
    }
    
    public function setCacheAdapter($cacheAdapter) : GAC {
        if (is_object($cacheAdapter)) {
            if (!in_array('DancasDev\\GAC\\Adapters\\CacheAdapterInterface', class_implements($cacheAdapter))) {
                throw new CacheAdapterException('Invalid implementation: The cache adapter must implement CacheAdapterInterface.', 1);
            }
        }
        else {
            $cacheAdapter = (empty($cacheAdapter) || !is_string($cacheAdapter)) ? __DIR__ . DIRECTORY_SEPARATOR . 'writable' : $cacheAdapter;
            $this ->cacheAdapter = new CacheAdapter($cacheAdapter);
        }
        
        return $this;
    }

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
            return false;
        }
        
        $permissions = [];

        if ($fromCache) {
            $permissions = $this ->getPermissionsFromCache($this ->entityType, $this ->entityId, $onlyEnabled);
        }

        if (empty($permissions)) {
            $permissions = $this ->getPermissionsFromDB($this ->entityType, $this ->entityId, $onlyEnabled);
        }

        if (!empty($permissions) && $fromCache) {
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
     * @return Permission|bool Instancia de Permission con los datos del permiso, FALSE si no tiene permiso
     */
    public function hasPermission(string $moduleCode) : Permission|bool {
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
        if (!array_key_exists($moduleCode, $this ->permissions)) {
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
     * @return array Listado de permisos
     */
    protected function getPermissionsFromCache(string $entityType, string|int $entityId, bool $onlyEnabled = true) : array {
        $response = [];
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

        # Depurar restricciones
        $permissionsIds = [];
        $modulesAndCategories = ['category' => [], 'module' => []];
        foreach($permissions as $key => $permission) {
            // Formatear
            $permissions[$key]['feature'] = !empty($permission['feature']) ? explode(',', $permission['feature']) : [];
            $permissions[$key]['level'] = (int) $permission['level'];
            $permissions[$key]['restriction_list'] = [];
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

            $permissionsIds[$permission['id']] = $key;
        }

        if (!empty($rolePriority)) {
            usort($permissions, function(array $a, array $b) {
                return $a['priority'] <=> $b['priority']; // ordenar por prioridad
            });
        }

        // agregar restricciones
        $result = $this ->databaseAdapter ->getRestrictions(array_keys($permissionsIds), $onlyEnabled);
        foreach ($result as $restriction) {
            $key = $permissionsIds[$restriction['module_access_id']];
            try {
                $restriction['restriction_type'] = str_replace(' ', '_', $restriction['restriction_type']);
                if (array_key_exists($restriction['restriction_category'], $permissions[$key]['restriction_list'])) {
                    continue;
                }

                $permissions[$key]['restriction_list'][$restriction['restriction_category']] = [
                    'id' => $restriction['id'],
                    'type' => $restriction['restriction_type'],
                    'data' => json_decode($restriction['restriction_value'], true)
                ];
            } catch (\Throwable $th) {
                //todo: agregar un log de error
            }
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
                        'restriction_list' => $permission['restriction_list'],
                        'is_disabled' => $permission['is_disabled']
                    ];
                }
            }
        }

        return $response;
    }
}