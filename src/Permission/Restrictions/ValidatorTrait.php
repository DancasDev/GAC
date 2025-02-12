<?php

namespace DancasDev\GAC\Permission\Restrictions;

trait ValidatorTrait {
    protected string $validationType = ''; // Almacena el tipo de validación (por ejemplo, 'whitelist', 'blacklist', 'date_range').
    protected array $validationParams = []; // Almacena los parámetros necesarios para realizar la validación (por ejemplo, una lista de valores permitidos, fechas de inicio y fin).

    function __construct(string $type = '', array $data = []) {
        $this ->validationType = $type;
        $this ->validationParams = $data;
    }

    /**
     * Establecer tipo de validación
     * 
     * @param string $type - Tipo de validación
     * 
     * @return void
     */
    function setType(string $type) {
        $this ->validationType = $type;
    }

    /**
     * Establecer paramatros de la validación
     * 
     * @param string $params - Parametros del tipo de validación (ejemplo, lista con las direcciones IP permitidas)
     * 
     * @return void
     */
    function setParams(array $params) {
        $this ->validationParams = $params;
    }
    
    /**
     * Ejecutar validación
     * 
     * @param array $externalData - Data externa en base a la que se estara trabajando (ejemplo para validar una IP, es necesario que se proporcione la misma)
     * 
     * @return bool|null true = exito, false = fallo, null = tipo de validación no implementada
     */
    function run(array $externalData = []) : bool|null {
        if (!method_exists($this, $this ->validationType)) {
            return null;
        }

        return $this ->{$this ->validationType}($externalData);
    }

    /// Utilidades

    /**
     * validar integridad de datos (internos y externo)
     * 
     * @param array $data - Data
     * @param array $keys - key que deben de estar presente
     * 
     * @return bool
     */
    function validateParamsIntegrity(array $data, array $keys) : bool {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                return false;
            }
        }

        return true;
    }
}