<?php

namespace DancasDev\GAC\Restrictions;

use DancasDev\GAC\Restrictions\ByDate;
use DancasDev\GAC\Restrictions\ByEntity;

class Restrictions {
    protected array $list = [];
    protected static array $restrictionMap = [
        'by_date'   => ByDate::class,
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

        if (!array_key_exists($categoryCode, self::$restrictionMap)) {
            throw new \Exception('No handler defined for restriction category: "' . $categoryCode . '"', 1);
        }

        $className = self::$restrictionMap[$categoryCode];
        $data = $this->list[$categoryCode];

        return new $className($data);
    }

    /**
     * Registrar una nueva clase de restricción
     * 
     * @param string $alias - Alias para la clase de restricción
     * @param string $className - Nombre completo de la clase de restricción
     * 
     * @return void
     */
    public static function register(string $alias, string $className): void {
        if (!is_subclass_of($className, Restriction::class)) {
            throw new \InvalidArgumentException('The class "' . $className . '" must extend the base Restriction class.', 1);
        }
        self::$restrictionMap[$alias] = $className;
    }
}