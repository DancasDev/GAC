<?php

namespace DancasDev\GAC;

class Utils {
    /**
     * Valida si un string representa un scope path sintácticamente correcto para GAC.
     *
     * REGLAS DE SINTAXIS VÁLIDA:
     *
     * 1. NULL PERMITIDO:
     *    - NULL es un valor válido. Significa "sin scope estático asignado; heredar dinámicamente de gac_role_entity".
     *    - Retorna TRUE para NULL.
     *
     * 2. FORMATO GENERAL:
     *    - El string puede contener UNO o VARIOS paths separados por coma (",").
     *    - Los espacios en blanco alrededor de cada path y de las comas se limpian (trim).
     *    - Un string vacío (""), nulo de contenido o compuesto solo de comas/espacios NO es válido -> FALSE.
     *    - Comas consecutivas (",,") o comas iniciales/finales sin contenido válido -> FALSE.
     *
     * 3. PATH INDIVIDUAL (reglas que aplican a cada path entre comas):
     *    a) WILDCARD GLOBAL:
     *       - El string "*" es válido y representa alcance global.
     *       - Inválidos: "**", "* / *", "*.*"
     *    b) PATHS JERÁRQUICOS:
     *       - Segmentos separados por "/" no vacíos (el doble slash "//" es estrictamente inválido).
     *       - Caracteres permitidos por segmento: alfanuméricos (a-z, A-Z, 0-9), guion ("-"),
     *         guion bajo ("_") y punto (".").
     *       - No se permiten caracteres especiales ni espacios (ej: "@", "#", "?", "&", "=", "%", etc.).
     *       - Longitud máxima por path individual: 512 caracteres.
     *       - Longitud máxima total del string: 2048 caracteres.
     *    c) WILDCARD DE PREFIJO:
     *       - Solo se permite al final del path como último segmento: ej. "/sucursal/*".
     *       - Válidos: "/sucursal/*", "sucursal/*", "/empresa/sucursal/*", "/api/v1/documents/*"
     *       - Inválidos: "/sucursal/* /depto" (wildcard intermedio), "/ *" (wildcard sin prefijo; usar "*").
     *    d) TRAILING SLASH:
     *       - Un path no puede terminar en "/" salvo si forma parte del wildcard final.
     *       - Inválidos: "/sucursal/", "sucursal/"
     *       - Válidos: "/sucursal", "sucursal", "/sucursal/*", "sucursal/*"
     *
     * 4. MULTI-PATH:
     *    - Múltiples paths se separan por coma: "/a,/b,/c/*" o "a,b,c/*"
     *    - No se permiten paths duplicados dentro del mismo string -> FALSE.
     *    - Cada path individual debe cumplir todas las reglas descritas.
     *
     * 5. SEGURIDAD (Principio de Menor Privilegio):
     *    - Ante cualquier irregularidad sintáctica o carácter no permitido, la función retorna FALSE
     *      para evitar fallos de bypass o inyecciones de rutas en tiempo de ejecución.
     *
     * @param string|null $path El scope path a validar. NULL es aceptado y retorna TRUE.
     * @return bool TRUE si es NULL o cumple con la sintaxis de scope; FALSE si es inválido.
     */
    public static function scopeValidate(?string $path): bool {
        if ($path === null) {
            return true;
        }

        $length = strlen($path);
        if ($length === 0 || $length > 2048) {
            return false;
        }

        $rawParts = explode(',', $path);
        $parts = [];

        foreach ($rawParts as $raw) {
            $trimmed = trim($raw);
            if ($trimmed === '') {
                return false;
            }
            $parts[] = $trimmed;
        }

        if (empty($parts)) {
            return false;
        }

        if (count($parts) !== count(array_unique($parts))) {
            return false;
        }

        $segPattern = '[a-zA-Z0-9_\-\.]+';
        $pathRegex = '/^(\*|(\/?' . $segPattern . ')(\/' . $segPattern . ')*(\\/\*)?)$/';

        foreach ($parts as $part) {
            if (strlen($part) > 512) {
                return false;
            }

            if ($part === '/*') {
                return false;
            }

            if (str_contains($part, '//')) {
                return false;
            }

            if (!preg_match($pathRegex, $part)) {
                return false;
            }

            if (str_contains($part, '*') && !str_ends_with($part, '/*') && $part !== '*') {
                return false;
            }
        }

        return true;
    }

    /**
     * Evalúa si alguna de las rutas contenidas en $newScopePath ya se encuentra presente
     * en los registros existentes de la base de datos para la misma entidad y módulo/tipo.
     *
     * Acepta directamente resultados asociativos de BD (PDO fetchAll), arreglos de strings o un string único.
     *
     * @param array|string|null $existingRecords Registros de BD (ej: [['scope_path' => '...']]) o arreglo de strings
     * @param string|null $newScopePath Nuevo scope path que se pretende registrar
     * @return bool TRUE si existe al menos una ruta duplicada (colisión), FALSE si está libre
     */
    public static function scopeHasCollision(array|string|null $existingRecords, ?string $newScopePath): bool {
        if ($newScopePath === null || $existingRecords === null) {
            return false;
        }

        // 1. Aplanar e indexar todas las rutas individuales existentes
        $existingPaths = [];
        $records = is_array($existingRecords) ? $existingRecords : [$existingRecords];

        foreach ($records as $item) {
            $raw = is_array($item) ? ($item['scope_path'] ?? ($item['s'] ?? null)) : (is_string($item) ? $item : null);

            if ($raw !== null && trim($raw) !== '') {
                foreach (explode(',', $raw) as $p) {
                    $trimmed = trim($p);
                    if ($trimmed !== '') {
                        $existingPaths[$trimmed] = true;
                    }
                }
            }
        }

        if (empty($existingPaths)) {
            return false;
        }

        // 2. Verificar si alguna ruta del nuevo scope colisiona con las existentes
        foreach (explode(',', $newScopePath) as $p) {
            $trimmed = trim($p);
            if ($trimmed !== '' && isset($existingPaths[$trimmed])) {
                return true; // Colisión detectada
            }
        }

        return false;
    }
}
