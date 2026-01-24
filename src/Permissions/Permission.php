<?php

namespace DancasDev\GAC\Permissions;

final class Permission {
    protected $id;
    protected $feature;
    protected $level;
    protected $module_code;
    protected $module_is_developing;
    
    protected $featureKeys = ['create' => '0', 'read' => '1', 'update' => '2', 'delete' => '3', 'trash' => '4', 'dev' => '5'];

    public function __construct(array $data) {
        $this->id = $data['i'] ?? null;
        $this->feature = $data['f'] ?? null;
        $this->level = $data['l'] ?? null;
        $this->module_code = $data['m'] ?? null;
        $this->module_is_developing = $data['d'] ?? null;
    }
    
    public function getId() : int {
        return $this->id;
    }

    public function getModuleCode() : string {
        return $this->module_code;
    }

    public function getFeature() : array {
        return $this->feature;
    }

    public function getLevel() : int {
        return $this->level;
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
}