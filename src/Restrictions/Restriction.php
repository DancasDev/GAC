<?php

namespace DancasDev\GAC\Restrictions;

class Restriction {
    protected array $list = [];
    protected array $methods = [];
    protected array $error = [];

    public function __construct(array $list) {
        $this->list = $list;
    }
    
    /**
     * Validar si un metodo de restricción existe
     * 
     * @param string $method - Nombre del método de restricción
     * @param bool $includeList - Incluir verificación en la lista de restricciones asignadas
     * 
     * @return bool
     */
    public function has(string $method, bool $includeList = true) : bool {
        if (!array_key_exists($method, $this ->methods) || !method_exists($this, $this ->methods[$method])) {
            return false;
        }
        elseif ($includeList && !array_key_exists($method, $this ->list)) {
            return false;
        }

        return  true;
    }

    /**
     * Verificar si la restricción se cumple
     * 
     * @param array $externalData - Datos externos necesarios para la validación de la restricción
     * 
     * @return bool
     */
    public function run(array $externalData) : bool {
        $this ->error = [];

        foreach ($this ->list as $method => $restrictions) {
            if (!$this ->has($method, false)) {
                continue;
            }

            foreach ($restrictions as $restriction) {
                $result = $this ->{$this ->methods[$method]}($restriction['d'], $externalData);
                if (!$result) {
                    $this ->error = [
                        'method' => $method,
                        'restriction' => $restriction,
                    ];

                    return false;
                }
            }
        }

        return true;
    }

     /**
     * validar integridad de datos
     * 
     * @param array $data - Data
     * @param array $list - array con los keys requeridos y los tipos de datos admitidos
     * 
     * @return bool
     */
    protected function validateDataIntegrity(array $data, array $list) : bool {
        foreach ($list as $key => $types) {
            if (!array_key_exists($key, $data)) {
                return false;
            }

            $valid = false;
            foreach ($types as $type) {
                if (gettype($data[$key]) === $type) {
                    $valid = true;
                    break;
                }
            }

            if (!$valid) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtener el último error ocurrido
     * 
     * @return array
     */
    public function getError() : array {
        return $this ->error;
    }
}