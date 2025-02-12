<?php

namespace DancasDev\GAC\Permission\Restrictions;

use DancasDev\GAC\Permission\Restrictions\ValidatorTrait;

class EntityValidator {
    use ValidatorTrait;
    
    /**
     * Validacion de lista blanca
     * 
     * @param array $externalData - Lista a validar
     * 
     * @return bool
     */
    function whitelist(array $externalData) : bool {
        foreach ($externalData as $id) {
            if (!in_array($id, $this->validationParams)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validacion de lista negra
     * 
     * @param array $externalData - Lista a validar
     * 
     * @return bool
     */
    function blacklist(array $externalData) : bool {
        foreach ($externalData as $id) {
            if (in_array($id, $this->validationParams)) {
                return false;
            }
        }

        return true;
    }
}