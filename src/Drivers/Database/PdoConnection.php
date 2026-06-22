<?php

namespace DancasDev\GAC\Drivers\Database;

use PDO;

class PdoConnection implements ConnectionInterface {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function param(?int $index = null): string {
        return '?';
    }

    public function prepare(string $query): StatementInterface|false {
        $stmt = $this->pdo->prepare($query);
        if ($stmt === false) {
            return false;
        }
        return new PdoStatement($stmt);
    }

    public function exec(string $statement): int|false {
        return $this->pdo->exec($statement);
    }

    public function lastInsertId(?string $name = null): string|false {
        return $this->pdo->lastInsertId($name);
    }
}
