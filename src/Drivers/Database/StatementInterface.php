<?php

namespace DancasDev\GAC\Drivers\Database;

interface StatementInterface {
    public const FETCH_ASSOC = 2;

    public function execute(?array $params = null): bool;
    public function fetchAll(int $mode = self::FETCH_ASSOC): array;
}
