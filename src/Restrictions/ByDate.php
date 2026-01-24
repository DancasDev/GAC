<?php

namespace DancasDev\GAC\Restrictions;

use DancasDev\GAC\Restrictions\Restriction;

final class ByDate extends Restriction {
    protected array $methods = [
        'before' => 'before',
        'in_range' => 'inRange',
        'out_range' => 'outRange',
        'after' => 'after',
    ];
    
    //  Metodos
    /**
     * Permiter antes de una fecha específica
     * 
     * @return bool
     */
    public function before(array $internalData, array $externalData) : bool {
        # Procesar datos
        // internos
        if ($this ->validateDataIntegrity($internalData, ['d' => ['string']])) {
            $internalData['d'] = $this ->formatDate($internalData['d']);
            if (!$internalData['d']) {
                return false;
            }
        }
        else {
            return false;
        }
        // externos
        if(!$this ->validateDataIntegrity($externalData, ['date' => ['integer']])) {
            return false;
        }

        # Validar        
        if ($externalData['date'] >= $internalData['d']) {
            return false;
        }

        return true;
    }
    
    /**
     * Permitir dentro de un rango de fechas
     * 
     * @return bool
     */
    public function inRange(array $internalData, array $externalData) : bool {
        # Procesar datos
        // internos
        if ($this ->validateDataIntegrity($internalData, ['sd' => ['string'], 'ed' => ['string']])) {
            $internalData['sd'] = $this ->formatDate($internalData['sd']);
            $internalData['ed'] = $this ->formatDate($internalData['ed']);
            if (!$internalData['sd'] || !$internalData['ed']) {
                return false;
            }
        }
        else {
            return false;
        }
        // externos
        if(!$this ->validateDataIntegrity($externalData, ['date' => ['integer']])) {
            return false;
        }

        # Validar
        if (!($externalData['date'] >= $internalData['sd'] && $externalData['date'] <= $internalData['ed'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Permitir fuera de un rango de fechas
     * 
     * @return bool
     */
    public function outRange(array $internalData, array $externalData) : bool {
        # Procesar datos
        // internos
        if ($this ->validateDataIntegrity($internalData, ['sd' => ['string'], 'ed' => ['string']])) {
            $internalData['sd'] = $this ->formatDate($internalData['sd']);
            $internalData['ed'] = $this ->formatDate($internalData['ed']);
            if (!$internalData['sd'] || !$internalData['ed']) {
                return false;
            }
        }
        else {
            return false;
        }
        // externos
        if(!$this ->validateDataIntegrity($externalData, ['date' => ['integer']])) {
            return false;
        }

        # Validar
        if ($externalData['date'] >= $internalData['sd'] && $externalData['date'] <= $internalData['ed']) {
            return false;
        }

        return true;
    }
    
    /**
     * Permitir después de una fecha específica
     * 
     * @return bool
     */
    public function after(array $internalData, array $externalData) : bool {
        # Procesar datos
        // internos
        if ($this ->validateDataIntegrity($internalData, ['d' => ['string']])) {
            $internalData['d'] = $this ->formatDate($internalData['d']);
            if (!$internalData['d']) {
                return false;
            }
        }
        else {
            return false;
        }
        // externos
        if(!$this ->validateDataIntegrity($externalData, ['date' => ['integer']])) {
            return false;
        }

        # Validar         
        if ($externalData['date'] <= $internalData['d']) {
            return false;
        }

        return true;
    }
    
    // Utilidades
    /**
     * Formatear fecha (se puedo utilizar comodines)
     * 
     * @param string $date - Fecha a formatear
     * @param bool $toTime - Retornar como fecha unix
     * 
     * @return string|int
     */
     function formatDate(string $date, bool $toTime = true) : string|int {
        $currentDate = date('Y-m-d');
        $currentDate = explode('-', $currentDate);
        
        $date = str_replace('%Y', $currentDate['0'], $date);
        $date = str_replace('%M', $currentDate['1'], $date);
        $date = str_replace('%D', $currentDate['2'], $date);

        return $toTime ? strtotime($date) : $date;
    }
}