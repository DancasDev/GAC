<?php

namespace DancasDev\GAC\Permissions;

class Permission {
    protected $id;
    protected $feature;
    protected $level;
    protected $module_code;
    protected $module_is_developing;
    
    protected $featureKeys = ['create' => 1, 'read' => 2, 'update' => 4, 'delete' => 8, 'trash' => 16, 'dev' => 32];

    public function __construct(array $data) {
        $this->id = $data['i'] ?? null;
        $this->feature = $data['f'] ?? null;
        $this->level = $data['l'] ?? null;
        $this->module_code = $data['m'] ?? null;
        $this->module_is_developing = $data['d'] ?? null;
    }
    
    public function getId() : ?int {
        return $this->id;
    }

    public function getModuleCode() : ?string {
        return $this->module_code;
    }

    public function getFeature() : ?int {
        return empty($this->feature) ? null : (int) $this->feature;
    }

    public function getLevel() : ?int {
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
        if (empty($feature)) {
            return false;
        }

        $bits = (int) $this->feature;
        $feature = (array) $feature;
        foreach ($feature as $value) {
            $mask = $this->featureKeys[$value] ?? ((int) $value);
            if (!($bits & $mask)) {
                return false;
            }
        }

        return true;
    }
}
