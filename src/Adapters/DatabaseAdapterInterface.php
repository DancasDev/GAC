<?php

namespace DancasDev\GAC\Adapters;

interface DatabaseAdapterInterface {
    /**
     * Esta función recupera los roles asignados a una entidad específico.
     *
     * @param string $entityType Tipo de entidad ('1' para usuario, '2' para cliente).
     * @param string|int $entityId Identificador de la entidad.
     * 
     * @return array Arreglo de roles asignados a la entidad. 
     * 
     * La estructura del arreglo es la siguiente:
     *  - id: Identificador del rol.
     *  - code: Código del rol.
     *  - priority: Prioridad del rol
     */
    public function getRoles(string $entityType, string|int $entityId): array;
  
    /**
     * Esta función recupera los permisos asociados a una entidad (usuario o cliente), considerando tanto los permisos directos de la entidad como los permisos indirectos heredados de los roles asignados.
     *
     * @param string $entityType Tipo de entidad ('1' para usuario, '2' para cliente).
     * @param string|int $entityId Identificador de la entidad.
     * @param array $roleIds Arreglo de identificadores de roles (opcional).
     * 
     * @return array Arreglo de permisos (sin procesar).
     * 
     * La estructura del arreglo depende de la información almacenada en la base de datos, pero podría incluir campos como:
     *  - id: Identificador del permiso.
     *  - from_entity_type: Tipo de entidad que posee el permiso ('0' para rol , '1' para usuario, '2' para cliente).
     *  - from_entity_id: Identificador de la entidad que posee el permiso.
     *  - to_entity_type: Tipo de entidad a la que aplica el permiso ('0' para categoría de módulo, '1' para módulo).
     *  - to_entity_id: Identificador de la entidad a la que aplica el permiso.
     *  - feature: String separado por comas con las características permitidas ( '0' para Crear, '1' para Leer, '2' para Actualizar, '3' para Eliminar, '4' para acceso a la papelera (valor funciona en combinación con los valores 1, 2 y 3), '5' para acceso al modo desarrollo).
     *  - level: Nivel del permiso ('0' es Bajo, '1' es Normal, '2' es Alto).
     */
    public function getPermissions(string $entityType, string|int $entityId, array $roleIds = []): array;

    /**
     * Esta función recupera las restricciones asociadas a una entidad (usuario o cliente), considerando tanto los permisos directos de la entidad como los permisos indirectos heredados de los roles asignados.
     *
     * @param string $entityType Tipo de entidad ('1' para usuario, '2' para cliente).
     * @param string|int $entityId Identificador de la entidad.
     * @param array $roleIds Arreglo de identificadores de roles (opcional).
     * 
     * @return array Arreglo de restricciones (sin procesar).
     * 
     * La estructura del arreglo depende de la información almacenada en la base de datos, pero podría incluir campos como:
     *  - id: Identificador de la restricción.
     *  - entity_type: Tipo de entidad que posee e la restricción ('0' para rol , '1' para usuario, '2' para cliente, null para todos).
     *  - entity_id: Identificador de la entidad que posee e la restricción.
     *  - category_code: Código de la categoría de la restricción.
     *  - type_code: Código del tipo de restricción
     *  - data: Datos para validar la restricción (formato depende del tipo de restricción).
     */
    public function getRestrictions(string $entityType, string|int $entityId, array $roleIds = []): array;
    
    /**
     * Esta función recupera datos de módulos en función de los ids proporcionados de los modulos y/o categorías.
     * 
     * @param array $moduleIds - Arreglo de identificadores de módulos (opcional).
     * @param array $categoryIds - Arreglo de identificadores de categorías de módulos (opcional).
     * 
     * @return array Arreglo de datos de módulos y categorías.
     * 
     * La estructura del arreglo depende de la información almacenada en la base de datos, pero podría incluir campos como:
     *  - id: Identificador del módulo.
     *  - module_category_id: Identificador de la categoría de módulo.
     *  - code: Código del módulo.
     *  - is_developing: Indica si el módulo está en desarrollo ('0' es No, '1' es Sí).
     */
    public function getModulesData(array $categoryIds = [], array $moduleIds = []): array;
}