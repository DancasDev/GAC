<?php

namespace DancasDev\GAC\Drivers\Database;

class PgsqlStatement implements StatementInterface {
    private $conn;
    private string $stmtName;
    private $lastResult = null;

    public function __construct($conn, string $stmtName) {
        $this->conn = $conn;
        $this->stmtName = $stmtName;
    }

    public function execute(?array $params = null): bool {
        $result = @pg_execute($this->conn, $this->stmtName, $params ?? []);
        if ($result === false) {
            return false;
        }
        $this->lastResult = $result;
        return true;
    }

    public function fetchAll(int $mode = self::FETCH_ASSOC): array {
        if ($this->lastResult === null) {
            return [];
        }
        $rows = pg_fetch_all($this->lastResult, $mode === self::FETCH_ASSOC ? PGSQL_ASSOC : PGSQL_BOTH);
        return $rows ?: [];
    }
}
