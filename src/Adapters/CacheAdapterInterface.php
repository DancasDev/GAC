<?php

namespace DancasDev\GAC\Adapters;

interface CacheAdapterInterface {
  
  /**
   * Obtiene un valor almacenado en el caché.
   * 
   * @param string $key Clave del valor a obtener.
   * 
   * @return mixed Valor almacenado en el caché, o NULL si no se encuentra o ha expirado.
   */
  public function get(string $key): mixed;

  /**
   * Almacena un valor en el caché.
   * 
   * @param string $key Clave del valor a almacenar.
   * @param mixed $data Valor a almacenar.
   * @param int|null $ttl Tiempo de vida en segundos (opcional).
   * 
   * @return bool TRUE en caso de éxito, FALSE en caso de fallo.
   */
  public function save(string $key, mixed $data, ?int $ttl = 60): bool;

  /**
   * Elimina un valor del caché.
   * 
   * @param string $key Clave del valor a eliminar.
   * 
   * @return bool TRUE en caso de éxito, FALSE en caso de fallo.
   */
  public function delete(string $key): bool;

  /**
   * Elimina todos los valores del caché que coincidan con un patrón.
   * 
   * @param string $pattern - Patron con estilo "glob" para coincidir con las claves a eliminar.
   * 
   * @return int Número de elementos eliminados. 
   */
  public function deleteMatching(string $pattern) : int;

  /**
   * Elimina todos los valores del caché.
   * 
   * @return bool TRUE en caso de éxito, FALSE en caso de fallo.
   */
  public function clean() : bool;
}