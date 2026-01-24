<?php

namespace DancasDev\GAC\Restrictions;

use DancasDev\GAC\Restrictions\Restriction;

final class ByEntity extends Restriction {
    protected array $methods = [
        'allow' => 'allow',
        'deny' => 'deny',
    ];
    
    //  Metodos
    /**
     * Permitir entidades específicas
     * 
     * @return bool
     */
    public function allow(array $internalData, array $externalData) : bool {
        # Procesar datos
        // internos
        if (!$this ->validateDataIntegrity($internalData, ['l' => ['array']])) {
            return false;
        }
        // externos
        if(!$this ->validateDataIntegrity($externalData, ['entity' => ['string','integer']])) {
            return false;
        }

        # Validar        
        if (!in_array($externalData['entity'], $internalData['l'])) {
            return false;
        }

        return true;
    }

    /**
     * Denegar entidades específicas
     * 
     * @return bool
     */
    public function deny(array $internalData, array $externalData) : bool {
        # Procesar datos
        // internos
        if (!$this ->validateDataIntegrity($internalData, ['l' => ['array']])) {
            return false;
        }
        // externos
        if($this ->validateDataIntegrity($externalData, ['entity' => ['string','integer']])) {
            return false;
        }

        # Validar        
        if (in_array($externalData['entity'], $internalData['l'])) {
            return false;
        }

        return true;
    }
}