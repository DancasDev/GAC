<?php

namespace DancasDev\GAC\Drivers\Database;

class PdoStatement implements StatementInterface {
    private \PDOStatement $stmt;

    public function __construct(\PDOStatement $stmt) {
        $this->stmt = $stmt;
    }

    public function execute(?array $params = null): bool {
        return $this->stmt->execute($params);
    }

    public function fetchAll(int $mode = self::FETCH_ASSOC): array {
        return $this->stmt->fetchAll($mode);
    }
}
