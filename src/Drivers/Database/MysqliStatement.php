<?php

namespace DancasDev\GAC\Drivers\Database;

use mysqli_stmt;

class MysqliStatement implements StatementInterface {
    private mysqli_stmt $stmt;

    public function __construct(mysqli_stmt $stmt) {
        $this->stmt = $stmt;
    }

    public function execute(?array $params = null): bool {
        if ($params !== null) {
            $types = '';
            $bind = [&$types];
            foreach ($params as $k => $v) {
                $types .= is_int($v) ? 'i' : (is_float($v) ? 'd' : 's');
                $bind[] = &$params[$k];
            }
            $this->stmt->bind_param(...$bind);
        }
        return $this->stmt->execute();
    }

    public function fetchAll(int $mode = self::FETCH_ASSOC): array {
        $result = $this->stmt->get_result();
        if ($result === false) {
            return [];
        }
        return $result->fetch_all($mode === self::FETCH_ASSOC ? MYSQLI_ASSOC : MYSQLI_BOTH);
    }
}
