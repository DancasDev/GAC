<?php

namespace DancasDev\GAC\Drivers\Database;

interface ConnectionInterface {
    /**
     * Placeholder para el parámetro N (0-indexed).
     * Sin argumento usa contador interno auto-incremental.
     * Con argumento explícito devuelve el placeholder fijo sin modificar el contador.
     */
    public function param(?int $index = null): string;

    public function prepare(string $query): StatementInterface|false;
    public function exec(string $statement): int|false;
    public function lastInsertId(?string $name = null): string|false;
}
