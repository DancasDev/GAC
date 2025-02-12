<?php

namespace DancasDev\GAC\Permission\Restrictions;

use DancasDev\GAC\Permission\Restrictions\ValidatorTrait;

class DateValidator {
    use ValidatorTrait;

    /**
     * En el rango
     */
    function in_range(array $externalData) : bool {
        if (!$this ->validateParamsIntegrity($externalData, ['date'])) {
            return false;
        }
        elseif (!$this ->validateParamsIntegrity($this ->validationParams, ['start', 'end'])) {
            return false;
        }

        $this ->validationParams['start'] = $this ->formatDate($this ->validationParams['start']);
        $this ->validationParams['end'] = $this ->formatDate($this ->validationParams['end']);
        if (!$this ->validationParams['start'] || !$this ->validationParams['end']) {
            return false;
        }
        
        if (!($externalData['date'] >= $this ->validationParams['start'] && $externalData['date'] <= $this ->validationParams['end'])) {
            return false;
        }
        
        return true;
    }

    /**
     * Fuera del rango
     */
    function out_range(array $externalData) : bool {
        if (!$this ->validateParamsIntegrity($externalData, ['date'])) {
            return false;
        }
        elseif (!$this ->validateParamsIntegrity($this ->validationParams, ['start', 'end'])) {
            return false;
        }

        $this ->validationParams['start'] = $this ->formatDate($this ->validationParams['start']);
        $this ->validationParams['end'] = $this ->formatDate($this ->validationParams['end']);
        if (!$this ->validationParams['start'] || !$this ->validationParams['end']) {
            return false;
        }
        
        if ($externalData['date'] >= $this ->validationParams['start'] && $externalData['date'] <= $this ->validationParams['end']) {
            return false;
        }
        return true;
    }

    /**
     * Antes
     */
    function before(array $externalData) : bool {
        if (!$this ->validateParamsIntegrity($externalData, ['date'])) {
            return false;
        }
        elseif (!$this ->validateParamsIntegrity($this ->validationParams, ['date'])) {
            return false;
        }

        $this ->validationParams['date'] = $this ->formatDate($this ->validationParams['date']);
        if (!$this ->validationParams['date']) {
            return false;
        }
        
        if ($externalData['date'] >= $this ->validationParams['date']) {
            return false;
        }
        return true;
    }

    /**
     * Despues
     */
    function after(array $externalData) : bool {
        if (!$this ->validateParamsIntegrity($externalData, ['date'])) {
            return false;
        }
        elseif (!$this ->validateParamsIntegrity($this ->validationParams, ['date'])) {
            return false;
        }

        $this ->validationParams['date'] = $this ->formatDate($this ->validationParams['date']);
        if (!$this ->validationParams['date']) {
            return false;
        }
        
        if ($externalData['date'] <= $this ->validationParams['date']) {
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