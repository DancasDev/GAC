<?php

namespace DancasDev\GAC\Restrictions;

use DancasDev\GAC\Restrictions\ByDate;

class Restrictions {
    protected array $list = [];
    protected static array $restrictionMap = [
        'by_date'   => ByDate::class,
        'by_branch' => ByEntity::class,
    ];

    public function __construct(array $list) {
        $this->list = $list;
    }

    public function getList() : array {
        return $this ->list;
    }

    public function setList(array $list) : Restrictions {
        $this ->list = $list;
        return $this;
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

    /**
     * Ejecutar restricciones
     * 
     * @param array $externalData - Datos externos
     * 
     * @return bool|array 
     */
    public function run(array $externalData) : bool|array {
        $list = array_keys($this ->getList());
        foreach ($list as $code) {
            // Restricción presente?
            $restriction = $this->get($code);
            if (!$restriction) {
                continue;
            }
            // Datos externos presentes?
            if (!array_key_exists($code, $externalData) || !is_array($externalData[$code])) {
                continue;
            }

            // Ejecutar
            $result = $restriction->run($externalData[$code]);
            if (!$result) {
                $error = $restriction->getError();
                $error['restriction'] = $code;
                if ($code == 'by_date') {
                    if ($error['method'] == 'before' || $error['method'] == 'after') {
                        $error['data']['d'] = $restriction->formatDate($error['data']['d'] ?? '', false);
                    } elseif ($error['method'] == 'in_range' || $error['method'] == 'out_range') {
                        $error['data']['sd'] = $restriction->formatDate($error['data']['sd'] ?? '', false);
                        $error['data']['ed'] = $restriction->formatDate($error['data']['ed'] ?? '', false);
                    }
                }

                return $error;
            }
        }

        return false;
    }
}

