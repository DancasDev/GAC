<?php

namespace DancasDev\GAC\Drivers\Database;

use mysqli;

class MysqliConnection implements ConnectionInterface {
    private mysqli $conn;

    public function __construct(mysqli $conn) {
        $this->conn = $conn;
    }

    public function param(?int $index = null): string {
        return '?';
    }

    public function prepare(string $query): StatementInterface|false {
        $stmt = $this->conn->prepare($query);
        if ($stmt === false) {
            return false;
        }
        return new MysqliStatement($stmt);
    }

    public function exec(string $statement): int|false {
        if ($this->conn->real_query($statement)) {
            $affected = $this->conn->affected_rows;
            return $affected === -1 ? 0 : $affected;
        }
        return false;
    }

    public function lastInsertId(?string $name = null): string|false {
        return (string) $this->conn->insert_id;
    }
}
