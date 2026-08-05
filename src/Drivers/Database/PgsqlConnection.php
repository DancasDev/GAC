<?php

namespace DancasDev\GAC\Drivers\Database;

class PgsqlConnection implements ConnectionInterface {
    private $conn;
    private int $paramCounter = 0;
    private int $stmtCounter = 0;
    private ?string $pendingStmtName = null;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function param(?int $index = null): string {
        if ($index !== null) {
            return '$' . ($index + 1);
        }
        return '$' . (++$this->paramCounter);
    }

    public function prepare(string $query): StatementInterface|false {
        $this->paramCounter = 0;
        $this->stmtCounter++;
        $stmtName = 'gac_' . $this->stmtCounter;
        $result = @pg_prepare($this->conn, $stmtName, $query);
        if ($result === false) {
            return false;
        }
        return new PgsqlStatement($this->conn, $stmtName);
    }

    public function exec(string $statement): int|false {
        $result = @pg_query($this->conn, $statement);
        if ($result === false) {
            return false;
        }
        return pg_affected_rows($result);
    }

    public function lastInsertId(?string $name = null): string|false {
        if ($name === null) {
            $result = @pg_query($this->conn, 'SELECT lastval()');
        } else {
            $result = @pg_query($this->conn, "SELECT currval('" . pg_escape_string($this->conn, $name) . "')");
        }
        if ($result === false) return false;
        $row = pg_fetch_row($result);
        return $row[0] ?? false;
    }
}
