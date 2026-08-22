<?php

namespace DancasDev\GAC\Permissions;

class Permission {
    protected $id;
    protected $feature;
    protected $level;
    protected $module_code;
    protected $module_is_developing;
    protected $payload;

    protected $featureKeys = ['create' => 1, 'read' => 2, 'update' => 4, 'delete' => 8, 'trash' => 16, 'dev' => 32];

    public function __construct(array $data) {
        $this->id = $data['i'] ?? null;
        $this->feature = $data['f'] ?? null;
        $this->level = $data['l'] ?? null;
        $this->module_code = $data['m'] ?? null;
        $this->module_is_developing = $data['d'] ?? null;
        $this->payload = $data['p'] ?? null;
    }
    
    public function getId() : ?int {
        return $this->id;
    }

    public function getModuleCode() : ?string {
        return $this->module_code;
    }

    public function getFeature() : ?int {
        return empty($this->feature) ? null : (int) $this->feature;
    }

    public function getLevel() : ?int {
        return $this->level;
    }

    /**
     * Datos extra del permiso (columna `payload`, JSON).
     *
     * @return array|null Array decodificado del payload, NULL si no tiene
     */
    public function getPayload() : ?array {
        return $this->payload;
    }

    /**
     * Valida si el módulo está marcado en modo desarrollo
     * 
     * @return bool
     */
    public function moduleIsDeveloping() : bool {
        return $this->module_is_developing == '1';
    }

    /**
     * Valida si el módulo es accesible para la entidad:
     * - Si el módulo está en desarrollo, requiere obligatoriamente poseer la acción 'dev'.
     * - Si no se especifica $feature: requiere al menos una acción operativa (create, read, update, delete, trash).
     * - Si se especifica $feature: valida que posea dicha(s) acción(es) requerida(s).
     *
     * @param string|array|int|null $feature Característica(s) específica(s) a validar (opcional)
     * @return bool
     */
    public function isAllowed(string|array|int|null $feature = null) : bool {
        if ($this->moduleIsDeveloping() && !$this->hasFeature('dev')) {
            return false;
        }

        $bits = (int) $this->feature;

        if ($feature === null || $feature === '') {
            return ($bits & 31) > 0;
        }

        return $this->hasFeature($feature);
    }

    /**
     * Verificar si existe acceso a determinadas características
     * 
     * @param string|array|int $feature - Características a validar
     * 
     * @return bool TRUE si tiene acceso, FALSE si no tiene acceso
     */
    public function hasFeature(string|array|int $feature) : bool {
        if (empty($feature)) {
            return false;
        }

        $bits = (int) $this->feature;
        $feature = (array) $feature;
        foreach ($feature as $value) {
            $mask = $this->featureKeys[$value] ?? ((int) $value);
            if (!($bits & $mask)) {
                return false;
            }
        }

        return true;
    }
}
