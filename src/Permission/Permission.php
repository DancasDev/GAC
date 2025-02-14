<?php

namespace DancasDev\GAC\Permission;

use DancasDev\GAC\Permission\Restrictions\EntityValidator;
use DancasDev\GAC\Permission\Restrictions\DateValidator;

final class Permission {
    protected $id;
    protected $feature;
    protected $restriction_list;
    protected $level;
    protected $is_disabled;
    protected $module_id;
    protected $module_code;
    protected $module_is_developing;
    
    protected $featureKeys = ['create' => '0', 'read' => '1', 'update' => '2', 'delete' => '3', 'trash' => '4', 'dev' => '5'];

    public function __construct(array $data) {
        $this->id = $data['id'] ?? null;
        $this->feature = $data['feature'] ?? null;
        $this->restriction_list = $data['restriction_list'] ?? null;
        $this->level = $data['level'] ?? null;
        $this->is_disabled = $data['is_disabled'] ?? null;
        $this->module_id = $data['module_id'] ?? null;
        $this->module_code = $data['module_code'] ?? null;
        $this->module_is_developing = $data['module_is_developing'] ?? null;
    }
    
    public function getId() : int {
        return $this->id;
    }

    public function getModuleId() : int {
        return $this->module_id;
    }

    public function getModuleCode() : string {
        return $this->module_code;
    }

    public function getFeature() : array {
        return $this->feature;
    }

    public function getRestrictionList() : array {
        return $this->restriction_list;
    }

    public function getLevel() : int {
        return $this->level;
    }

    /**
     * Validar si el permiso esta inhabilitado
     * 
     * @return bool
     */
    public function isDisabled() : bool {
        return $this->is_disabled == '1';
    }

    /**
     * Validar si el modulo esta en modo desarrollo
     * 
     * @return bool
     */
    public function moduleIsDeveloping() : bool {
        return $this->module_is_developing == '1';
    }

    /**
     * Verificar si existe acceso a determinadas caracteristicas
     * 
     * @param string|array $feature - Características a validar
     * 
     * @return bool TRUE si tiene acceso, FALSE si no tiene acceso
     */
    public function hasFeature(string|array $feature) : bool {
        // Validar integridad
        if (empty($this ->feature) || !is_array($this ->feature)) {
            return false;
        }
        elseif (empty($feature)) {
            return false;
        }

        // Validar permiso
        $feature = is_array($feature) ? $feature : [$feature];
        foreach ($feature as $value) {
            $value = $this ->featureKeys[$value] ?? $value;
            if (!in_array($value, $this->feature)) {
                return false;
            }
        }

        return true;
    }

    /**
     * validar si se tiene una restrincción
     * 
     * @param string $restrictionKey - Key de la restricción
     * 
     * @param bool
     */
    public function hasRestriction(string $restrictionKey) : bool {
        return array_key_exists($restrictionKey, $this->restriction_list);
    }

    /**
     * Obtener la lista de restricciones
     * 
     * @return array Listado
     */
    public function getRestrictions() : array {
        return $this->restriction_list ?? [];
    }

    /// Validaciones de restricciones
    function validateEntityRestriction(string $entityType, string|int|array $entityIdList) : bool|null {
        if (empty($this->restriction_list) || !is_array($this->restriction_list)) {
            return true;
        }
        elseif (empty($this->restriction_list[$entityType]) || !is_array($this->restriction_list[$entityType])) {
            return true;
        }
        
        $entityIdList = !is_array($entityIdList) ? [$entityIdList] : $entityIdList;

        $validator = new EntityValidator();
        $validator ->setType($this->restriction_list[$entityType]['type']);
        $validator ->setParams($this->restriction_list[$entityType]['data']);

        return $validator ->run($entityIdList);
    }

    function validateDateRestriction(int $date = null) : bool|null {
        if (empty($this->restriction_list) || !is_array($this->restriction_list)) {
            return true;
        }
        elseif (empty($this->restriction_list['date']) || !is_array($this->restriction_list['date'])) {
            return true;
        }

        $validator = new DateValidator();
        $validator ->setType($this->restriction_list['date']['type']);
        $validator ->setParams($this->restriction_list['date']['data']);

        return $validator ->run(['date' => $date ?? time()]);
    }
}
