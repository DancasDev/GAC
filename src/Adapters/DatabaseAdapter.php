<?php

namespace DancasDev\GAC\Adapters;

use DancasDev\GAC\Adapters\DatabaseAdapterInterface;
use DancasDev\GAC\Exceptions\DatabaseAdapterException;
use PDO;

class DatabaseAdapter implements DatabaseAdapterInterface {
    private $connection;

    public function __construct(PDO|array $params) {
        if ($params instanceof PDO) {
            $this->connection = $params;
        }
        elseif (is_array($params)) {
            foreach (['host', 'username', 'password', 'database'] as $key) {
                if (!array_key_exists($key, $params) || !is_string($params[$key])) {
                    throw new DatabaseAdapterException('Invalid connection parameters: you need to correctly provide the following parameters: host, username, password and database.', 1);
                }
            }

            try {
                $this ->connection = new PDO('mysql:host=' . $params['host'] . ';dbname=' . $params['database'], $params['username'], $params['password']);
            } catch (\Throwable $th) {
                throw new DatabaseAdapterException('Error connecting to database "' . $params['database'] . '"', 0, $th);
            }
        }
        else {
            throw new DatabaseAdapterException('Need to provide database adapter.', 1);
        }
    }
    
    public function __destruct() {
        $this ->destroyConnection();
    }

    /**
     * Destruye la conexión a la base de datos.
     * 
     * @return bool TRUE siempre.
     */
    public function destroyConnection() {
        $this ->connection = null;

        return true;
    }

    public function getRoles(string $entityType, string|int $entityId): array {
        $query = 'SELECT b.id, b.code, a.priority';
        $query .= ' FROM `gac_role_entity` AS a INNER JOIN `gac_role` AS b ON a.role_id = b.id';
        $query .= ' WHERE a.entity_type = :entity_type AND a.entity_id = :entity_id AND a.is_disabled = \'0\' AND b.is_disabled = \'0\' AND a.deleted_at IS NULL AND b.deleted_at IS NULL';
        $query .= ' ORDER BY a.priority ASC';
        $query = $this ->connection ->prepare($query);
        $query ->bindParam(':entity_type', $entityType, PDO::PARAM_INT);
        $query ->bindParam(':entity_id', $entityId, PDO::PARAM_INT);
        $query ->execute();

        return $query ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPermissions(string $entityType, string|int $entityId, array $roleIds = []): array {
        if($entityType === '0') {
            return [];
        }

        $query = 'SELECT id, from_entity_type, from_entity_id, to_entity_type, to_entity_id, feature, level';
        $query .= ' FROM `gac_module_access` WHERE ((`from_entity_type` = ? AND `from_entity_id` = ?)';
        foreach ($roleIds as $key => $id) {
            $query .= ' OR (`from_entity_type` = \'0\' AND `from_entity_id` = ?)';
        }

        $query .= ') AND `deleted_at` IS NULL AND `is_disabled` = \'0\'';
        $query .= ' ORDER BY `from_entity_type` DESC';
        $query = $this ->connection ->prepare($query);
        $query ->execute(array_merge([$entityType, $entityId], $roleIds));

        return $query ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRestrictions(string $entityType, string|int $entityId, array $roleIds = []): array {
        if($entityType === '0') {
            return [];
        }

        $query = 'SELECT a.id, a.entity_type, a.entity_id, c.code AS category_code, b.code AS type_code, a.data';
        $query .= ' FROM `gac_restriction` AS a INNER JOIN `gac_restriction_method` AS b ON a.restriction_method_id = b.id INNER JOIN `gac_restriction_category` AS c ON b.restriction_category_id = c.id';
        $query .= ' WHERE ((a.entity_type = ? AND a.entity_id = ?)';
        foreach ($roleIds as $key => $id) {
            $query .= ' OR (a.entity_type = \'0\' AND a.entity_id = ?)';
        }

        $query .= ') AND a.deleted_at IS NULL AND b.deleted_at IS NULL AND c.deleted_at IS NULL AND a.is_disabled = \'0\' AND b.is_disabled = \'0\' AND c.is_disabled = \'0\'';
        $query .= ' ORDER BY a.entity_type DESC';
        $query = $this ->connection ->prepare($query);
        $query ->execute(array_merge([$entityType, $entityId], $roleIds));

        return $query ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getModulesData(array $categoryIds = [], array $moduleIds = []): array {
        $hasModules = !empty($moduleIds);
        $hasCategories = !empty($categoryIds);
        if (!$hasModules && !$hasCategories) {
            return [];
        }

        $query = 'SELECT a.id, a.module_category_id, a.code, a.is_developing FROM gac_module AS a INNER JOIN gac_module_category AS b ON a.module_category_id = b.id WHERE (';
        if (!empty($categoryIds)) {
            $query .= 'a.module_category_id IN (' . implode(',', $categoryIds) . ')';
        }
        if ($hasModules) {
            $query .= ' OR a.id IN (' . implode(',', $moduleIds) . ')';
        }
        $query .= ') AND a.deleted_at IS NULL AND b.deleted_at IS NULL AND a.is_disabled = \'0\' AND b.is_disabled = \'0\'';

        $query = $this ->connection ->prepare($query);
        $query ->execute();

        return $query ->fetchAll(PDO::FETCH_ASSOC);
    }

    function getEntitiesByRoles(array $roleIds): array {
        if (empty($roleIds)) {
            return [];
        }

        $query = 'SELECT id, role_id, entity_type, entity_id FROM `gac_role_entity` WHERE role_id IN (' . implode(',', $roleIds) . ') AND is_disabled = \'0\' AND deleted_at IS NULL';
        $query = $this ->connection ->prepare($query);
        $query ->execute();

        return $query ->fetchAll(PDO::FETCH_ASSOC);
    }
}