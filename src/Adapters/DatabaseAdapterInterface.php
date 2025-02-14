<?php

namespace DancasDev\GAC\Adapters;

interface DatabaseAdapterInterface {
    /**
     * Esta función recupera los roles asignados a una entidad específico.
     *
     * @param string $entityType Tipo de entidad ('1' para usuario, '2' para token externo).
     * @param string|int $entityId Identificador de la entidad.
     * @param bool $onlyEnabled (opcional) Indica si solo se deben recuperar roles habilitados (predeterminado: true).
     * 
     * @return array Arreglo de roles asignados a la entidad. 
     * 
     * La estructura del arreglo es la siguiente:
     *  - id: Identificador del rol.
     *  - code: Código del rol.
     *  - priority: Prioridad del rol
     *  si $onlyEnabled es false, también puede incluir:
     *      - is_disabled: Indica si el rol está deshabilitado.
     *      - is_disabled_entity_role: Indica si la asociación de usuario-rol está deshabilitada.
     */
    public function getRoles(string $entityType, string|int $entityId, bool $onlyEnabled = true): array;
  
    /**
     * Esta función recupera los permisos asociados a una entidad (usuario o token externo), considerando tanto los permisos directos de la entidad como los permisos de sus roles asignados.
     *
     * @param string $entityType Tipo de entidad ('1' para usuario, '2' para token externo).
     * @param string|int $entityId Identificador de la entidad.
     * @param array $roleIds Arreglo de identificadores de roles (opcional).
     * @param bool $onlyEnabled (opcional) Indica si solo se deben recuperar permisos habilitados (predeterminado: true).
     * 
     * @return array Arreglo de permisos (sin procesar).
     * 
     * 
     * La estructura del arreglo depende de la información almacenada en la base de datos, pero podría incluir campos como:
     *  - id: Identificador del permiso.
     *  - from_entity_type: Tipo de entidad que posee el permiso ('0' para rol , '1' para usuario, '2' para token externo).
     *  - from_entity_id: Identificador de la entidad que posee el permiso.
     *  - to_entity_type: Tipo de entidad a la que aplica el permiso ('0' para categoría de módulo, '1' para módulo).
     *  - to_entity_id: Identificador de la entidad a la que aplica el permiso.
     *  - restriction_type: Tipo de restricción (NULL: Ninguno, '0': Lista negra, '1': lista blanca).
     *  - feature: String separado por comas con las características permitidas ( '0' para Crear, '1' para Leer, '2' para Actualizar, '3' para Eliminar, '4' para acceso a la papelera (valor funciona en combinación con los valores 1, 2 y 3), '5' para acceso al modo desarrollo).
     *  - level: Nivel del permiso ('0' es Bajo, '1' es Normal, '2' es Alto).
     *  - is_disabled: Indica si el permiso está deshabilitado ('0' es No, '1' es Sí).
     *  - created_at: Fecha de creación del permiso.
     *  - updated_at: Fecha de última actualización del permiso.
     */
    public function getPermissions(string $entityType, string|int $entityId, array $roleIds = [], bool $onlyEnabled = true): array;
  
    /**
     * Esta funcion recupera las restricciones de uno o mas accesos.
     * 
     * @param string|int|array $permissionIds Identificador(es) de permiso(s).
     * @param bool $onlyEnabled (opcional) Indica si solo se deben recuperar restricciones habilitadas (predeterminado: true).
     * 
     * @return array Arreglo de restricciones.
     * 
     * La estructura del arreglo depende de la información almacenada en la base de datos, pero podría incluir campos como:
     *  - id: Identificador de la restricción.
     *  - restriction_category: Categoría de la restricción.
     *  - restriction_type: Tipo de restricción (NULL: Ninguno, '0': Lista negra, '1': lista blanca).
     *  - restriction_value: Valor de la restricción.
     *  - is_disabled: Indica si la restricción está deshabilitada ('0' es No, '1' es Sí).
     * 
     */
    public function getRestrictions(string|int|array $permissionIds, bool $onlyEnabled = true): array;
    /**
     * Esta función recupera datos de módulos y sus categorías asociadas en función de los IDs proporcionados.
     * 
     * @param array $moduleCategoryIds Arreglo de identificadores de categorías de módulos (opcional).
     * @param array $moduleIds Arreglo de identificadores de módulos (opcional).
     * 
     * @return array Arreglo de datos de módulos y categorías.
     * 
     * La estructura del arreglo depende de la información almacenada en la base de datos, pero podría incluir campos como:
     *  - id: Identificador del módulo.
     *  - module_category_id: Identificador de la categoría de módulo.
     *  - code: Código del módulo.
     *  - is_developing: Indica si el módulo está en desarrollo ('0' es No, '1' es Sí).
     */
    public function getModulesAndCategories(array $moduleCategoryIds = [], array $moduleIds = []): array;
}