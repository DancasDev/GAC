<?php

namespace DancasDev\GAC\Restrictions;

use DancasDev\GAC\Restrictions\ByDate;
use DancasDev\GAC\Restrictions\ByEntity;

class Restrictions {
    protected array $list = [];
    protected array $categories = [
        'by_date' => ByDate::class,
        'by_branch' => ByEntity::class,
    ];

    public function __construct(array $list) {
        $this->list = $list;
    }

    /**
     * Verificar si se imponen restricciones de un tipo
     * 
     * @param string $categoryCode - Código de la categoría (ejemplo: 'by_date', 'by_ip', etc.)
     * 
     * @return bool
     */
    public function has(string $categoryCode) : bool {
        return array_key_exists($categoryCode, $this ->list);
    }

    /**
     * Obtener restricciones de la entidad
     * 
     * @param string $categoryCode - Código de la categoría (ejemplo: 'by_date', 'by_ip', etc.)
     * 
     * @return mixed Instancia de la clase de restricción correspondiente, NULL si no tiene restricciones de ese tipo
     */
    public function get(string $categoryCode) : mixed {
        if (!$this ->has($categoryCode)) {
            return null;
        }
        elseif (!is_array($this ->list[$categoryCode]) || empty($this ->list[$categoryCode])) {
            throw new \Exception('The restriction data for category "'. $categoryCode . '" is invalid.', 1);
        }

        if (!array_key_exists($categoryCode, $this ->categories)) {
            throw new \Exception('The restriction category "'. $categoryCode . '" is not supported.', 1);
        }

        $className = $this ->categories[$categoryCode];
        return new $className($this ->list[$categoryCode]);
    }
}