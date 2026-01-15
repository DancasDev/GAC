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
        if ($this ->validateDataIntegrity($internalData, ['date' => ['string']])) {
            $internalData['date'] = $this ->formatDate($internalData['date']);
            if (!$internalData['date']) {
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
        if ($externalData['date'] >= $internalData['date']) {
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
        if ($this ->validateDataIntegrity($internalData, ['start' => ['string'], 'end' => ['string']])) {
            $internalData['start'] = $this ->formatDate($internalData['start']);
            $internalData['end'] = $this ->formatDate($internalData['end']);
            if (!$internalData['start'] || !$internalData['end']) {
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
        if (!($externalData['date'] >= $internalData['start'] && $externalData['date'] <= $internalData['end'])) {
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
        if ($this ->validateDataIntegrity($internalData, ['start' => ['string'], 'end' => ['string']])) {
            $internalData['start'] = $this ->formatDate($internalData['start']);
            $internalData['end'] = $this ->formatDate($internalData['end']);
            if (!$internalData['start'] || !$internalData['end']) {
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
        if ($externalData['date'] >= $internalData['start'] && $externalData['date'] <= $internalData['end']) {
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
        if ($this ->validateDataIntegrity($internalData, ['date' => ['string']])) {
            $internalData['date'] = $this ->formatDate($internalData['date']);
            if (!$internalData['date']) {
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
        if ($externalData['date'] <= $internalData['date']) {
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
        $date = str_replace('%Y', date('Y'), $date);
        $date = str_replace('%M', date('m'), $date);
        $date = str_replace('%D', date('d'), $date);

        return $toTime ? strtotime($date) : $date;
    }
}