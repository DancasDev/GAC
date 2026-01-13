<?php

namespace DancasDev\GAC\Permissions;

use DancasDev\GAC\Permissions\Permission;

class Permissions {
    protected array $list = [];

    public function __construct(array $list) {
        $this->list = $list;
    }

    /**
     * Verificar si se tiene permiso para acceder a un módulo
     * 
     * @param string $moduleCode - Código del módulo
     * 
     * @return bool
     */
    public function has(string $moduleCode) : bool {
        return array_key_exists($moduleCode, $this ->list);
    }

    /**
     * Obtener permiso de la entidad
     * 
     * @param string $moduleCode - Código del módulo
     * 
     * @return Permission|null Instancia de Permission con los datos del permiso, NULL si no tiene permiso
     */
    public function get(string $moduleCode) : Permission|null {
        if (!$this ->hasPermission($moduleCode)) {
            return null;
        }
        elseif (!is_array($this ->list[$moduleCode]) || empty($this ->list[$moduleCode])) {
            throw new \Exception('The permission data for module "'. $moduleCode . '" is invalid.', 1);
        }

        return new Permission(array_merge($this ->list[$moduleCode], ['module_code' => $moduleCode]));   
    }
}