<?php

namespace DancasDev\GAC;

use PDO;

class Schema {
    private static array $options = [];

    public static function install(PDO $pdo, array $options = []): bool {
        self::$options = array_merge([
            'charset'       => 'utf8mb4',
            'collation'     => 'utf8mb4_unicode_ci',
            'show_comments' => true,
        ], $options);

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $driver = match ($driver) {
            'mysql', 'mariadb' => 'mysql',
            'pgsql'           => 'pgsql',
            default           => throw new \RuntimeException("Unsupported driver: $driver")
        };

        $method = 'ddl' . ucfirst($driver);
        $statements = [];

        foreach (self::tables() as $name => $def) {
            $def['columns']['deleted_at'] = ['type' => 'bigint'];
            $statements = array_merge($statements, self::$method($name, $def));
        }

        $pdo->exec('START TRANSACTION');
        try {
            foreach ($statements as $sql) {
                $pdo->exec($sql);
            }
            $pdo->exec('COMMIT');
            return true;
        } catch (\Throwable $e) {
            $pdo->exec('ROLLBACK');
            throw $e;
        }
    }

    public static function uninstall(PDO $pdo): bool {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $driver = match ($driver) {
            'mysql', 'mariadb' => 'mysql',
            'pgsql'           => 'pgsql',
            default           => throw new \RuntimeException("Unsupported driver: $driver")
        };

        $quote = fn(string $t) => $driver === 'pgsql' ? '"' . $t . '"' : '`' . $t . '`';
        $tables = array_keys(self::tables());

        if ($driver === 'mysql') {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        }

        foreach (array_reverse($tables) as $table) {
            $pdo->exec('DROP TABLE IF EXISTS ' . $quote($table) . ($driver === 'pgsql' ? ' CASCADE' : ''));
        }

        if ($driver === 'mysql') {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        return true;
    }

    private static function tables(): array {
        $en = fn(array $vals, string $d = '0') => ['type' => 'enum', 'vals' => $vals, 'notnull' => true, 'default' => $d];
        $b  = fn(string $type, int $len = null) => ['type' => $type, 'length' => $len];

        return [
            'gac_user' => [
                'columns' => [
                    'id'          => $b('serial'),
                    'username'    => $b('varchar', 60) + ['notnull' => true, 'comment' => 'Nombre de usuario unico para inicio de sesion'],
                    'password'    => $b('varchar', 255) + ['notnull' => true, 'comment' => 'Contrasena del usuario (DEBE almacenarse hasheada)'],
                    'is_disabled' => $en(['0', '1']) + ['comment' => '0=No, 1=Si'],
                ],
                'unique' => ['username'],
            ],
            'gac_client' => [
                'comment' => 'Directorio de tokens para aplicaciones externa',
                'columns' => [
                    'id'            => $b('serial'),
                    'client_id'     => $b('varchar', 255) + ['notnull' => true, 'comment' => 'Identificador publico del cliente (similar a un username)'],
                    'client_secret' => $b('varchar', 255) + ['notnull' => true, 'comment' => 'Secreto del cliente (DEBE almacenarse hasheado)'],
                    'is_disabled'   => $en(['0', '1']) + ['comment' => '0=No, 1=Si'],
                ],
                'unique' => ['client_id'],
            ],
            'gac_role' => [
                'columns' => [
                    'id'          => $b('serial'),
                    'code'        => $b('varchar', 30) + ['notnull' => true, 'comment' => 'Codigo unico del rol (ej: system_administrator)'],
                    'is_disabled' => $en(['0', '1']) + ['comment' => '0=No, 1=Si'],
                ],
                'unique' => ['code'],
            ],
            'gac_role_entity' => [
                'columns' => [
                    'id'          => $b('serial'),
                    'role_id'     => $b('int') + ['notnull' => true, 'comment' => 'Referencia al rol asignado'],
                    'entity_type' => ['type' => 'enum', 'vals' => ['1', '2'], 'notnull' => true, 'comment' => '1=Usuario, 2=Cliente'],
                    'entity_id'   => $b('int') + ['notnull' => true, 'comment' => 'ID de la entidad asignada'],
                    'priority'    => $b('tinyint', 1) + ['notnull' => true, 'default' => '0', 'comment' => '0=principal, >0=secundario'],
                    'is_disabled' => $en(['0', '1']) + ['comment' => '0=No, 1=Si'],
                ],
                'unique' => [
                    ['role_id', 'entity_type', 'entity_id'],
                    ['entity_type', 'entity_id', 'priority'],
                ],
                'index' => ['role_id', ['entity_type', 'entity_id']],
                'fk'    => [['role_id', 'gac_role', 'id']],
            ],
            'gac_module_category' => [
                'columns' => [
                    'id'          => $b('serial'),
                    'code'        => $b('varchar', 40) + ['notnull' => true, 'comment' => 'Codigo unico que identifica la categoria'],
                    'is_disabled' => $en(['0', '1']) + ['comment' => '0=No, 1=Si'],
                ],
                'unique' => ['code'],
            ],
            'gac_module' => [
                'columns' => [
                    'id'                 => $b('serial'),
                    'module_category_id' => $b('int') + ['notnull' => true, 'comment' => 'Categoria a la que pertenece el modulo'],
                    'code'               => $b('varchar', 40) + ['notnull' => true, 'comment' => 'Codigo unico del modulo'],
                    'is_developing'      => $en(['0', '1'], '1') + ['comment' => '0=No, 1=Si (modo desarrollo)'],
                    'is_disabled'        => $en(['0', '1']) + ['comment' => '0=No, 1=Si'],
                ],
                'unique' => ['code'],
                'index'  => ['module_category_id'],
                'fk'     => [['module_category_id', 'gac_module_category', 'id']],
            ],
            'gac_module_permission' => [
                'columns' => [
                    'id'               => $b('serial'),
                    'from_entity_type' => ['type' => 'enum', 'vals' => ['0', '1', '2'], 'notnull' => true, 'comment' => '0=Rol, 1=Usuario, 2=Cliente'],
                    'from_entity_id'   => $b('int') + ['notnull' => true, 'comment' => 'ID de la entidad que posee el permiso'],
                    'to_entity_type'   => ['type' => 'enum', 'vals' => ['0', '1'], 'notnull' => true, 'comment' => '0=Categoria, 1=Modulo'],
                    'to_entity_id'     => $b('int') + ['notnull' => true, 'comment' => 'ID de la entidad destino del permiso'],
                    'scope_path'       => $b('varchar', 255) + ['notnull' => true, 'default' => '*', 'comment' => 'Ruta jerarquica de alcance: * (global), empresaX, empresaX/SucursalA'],
                    'feature'          => $b('smallint', 5) + ['notnull' => true, 'default' => '0', 'comment' => 'Bitmask: 1=Crear, 2=Leer, 4=Actualizar, 8=Eliminar, 16=Papelera, 32=Modo desarrollo'],
                    'level'            => ['type' => 'enum', 'vals' => ['0', '1', '2'], 'notnull' => true, 'default' => '1', 'comment' => '0=Bajo, 1=Normal, 2=Alto'],
                    'is_disabled'      => $en(['0', '1']) + ['comment' => '0=No, 1=Si'],
                ],
                'unique' => [['from_entity_type', 'from_entity_id', 'to_entity_type', 'to_entity_id', 'scope_path']],
                'index'  => [
                    ['from_entity_type', 'from_entity_id'],
                    ['from_entity_type', 'from_entity_id', 'scope_path'],
                ],
            ],
            'gac_restriction' => [
                'columns' => [
                    'id'          => $b('serial'),
                    'entity_type' => ['type' => 'enum', 'vals' => ['0', '1', '2', '3'], 'notnull' => true, 'comment' => '0=Rol, 1=Usuario, 2=Cliente, 3=Todos'],
                    'entity_id'   => $b('int') + ['notnull' => true, 'comment' => 'ID de la entidad'],
                    'scope_path'  => $b('varchar', 255) + ['notnull' => true, 'default' => '*', 'comment' => 'Ruta jerarquica de alcance (mismo concepto que gac_module_permission)'],
                    'type'        => $b('varchar', 30) + ['notnull' => true, 'comment' => 'Tipo: date, ip'],
                    'rule'        => $b('varchar', 30) + ['notnull' => true, 'comment' => 'Regla segun tipo: date=before/after/in_range/out_range, ip=allow/deny'],
                    'config'      => ['type' => 'longtext', 'notnull' => true, 'comment' => 'Configuracion en JSON'],
                    'is_disabled' => $en(['0', '1']) + ['comment' => '0=No, 1=Si'],
                ],
                'unique' => [['entity_type', 'entity_id', 'scope_path', 'type', 'rule']],
                'index'  => [['type', 'rule'], ['entity_type', 'entity_id', 'scope_path']],
            ],
        ];
    }

    // ─── MySQL ───────────────────────────────────────────────────────────────

    private static function ddlMysql(string $table, array $def): array {
        $charset = self::$options['charset'] ?? 'utf8mb4';
        $collation = self::$options['collation'] ?? 'utf8mb4_unicode_ci';
        $lines = [];
        $n = 1;

        foreach ($def['columns'] as $name => $col) {
            $lines[] = '  `' . $name . '` ' . self::mysqlColType($col);
        }

        $lines[] = '  PRIMARY KEY (`id`)';

        // UNIQUE — use numeric suffix to avoid long names
        if (!empty($def['unique'])) {
            foreach ($def['unique'] as $u) {
                $cols = is_array($u) ? $u : [$u];
                $lines[] = '  UNIQUE KEY `uk_' . $n . '` (`' . implode('`,`', $cols) . '`)';
                $n++;
            }
        }

        // INDEX
        if (!empty($def['index'])) {
            foreach ($def['index'] as $ix) {
                $cols = is_array($ix) ? $ix : [$ix];
                $lines[] = '  KEY `idx_' . implode('_', $cols) . '` (`' . implode('`,`', $cols) . '`)';
            }
        }

        $comment = isset($def['comment']) ? " COMMENT='" . addslashes($def['comment']) . "'" : '';
        $statements = [];
        $statements[] = "CREATE TABLE IF NOT EXISTS `$table` (\n" . implode(",\n", $lines) . "\n) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=$collation$comment;";

        // FK
        if (!empty($def['fk'])) {
            foreach ($def['fk'] as $fk) {
                $fkn = 'fk_' . $table . '_' . $fk[0];
                $statements[] = "ALTER TABLE `$table` ADD CONSTRAINT `$fkn` FOREIGN KEY (`$fk[0]`) REFERENCES `$fk[1]` (`$fk[2]`);";
            }
        }

        return $statements;
    }

    private static function mysqlColType(array $col): string {
        $collation = self::$options['collation'] ?? 'utf8mb4_unicode_ci';
        $nn = $col['notnull'] ?? false;
        $hasD = array_key_exists('default', $col);
        $d = $hasD ? " DEFAULT '" . $col['default'] . "'" : ($nn ? '' : ' DEFAULT NULL');
        $cmt = (self::$options['show_comments'] ?? true) && isset($col['comment']) ? " COMMENT '" . addslashes($col['comment']) . "'" : '';

        $type = match ($col['type']) {
            'serial'   => 'INT NOT NULL AUTO_INCREMENT',
            'int'      => 'INT' . ($nn ? ' NOT NULL' : '') . $d,
            'bigint'   => 'BIGINT' . ($nn ? ' NOT NULL' : '') . $d,
            'smallint' => 'SMALLINT' . (isset($col['length']) ? '(' . $col['length'] . ')' : '') . ($nn ? ' NOT NULL' : '') . $d,
            'tinyint'  => 'TINYINT' . (isset($col['length']) ? '(' . $col['length'] . ')' : '') . ($nn ? ' NOT NULL' : '') . $d,
            'varchar'  => 'VARCHAR(' . $col['length'] . ')' . ($nn ? ' NOT NULL' : '') . $d . " COLLATE $collation",
            'text'     => 'TEXT' . ($nn ? ' NOT NULL' : '') . $d . " COLLATE $collation",
            'longtext' => 'LONGTEXT' . ($nn ? ' NOT NULL' : '') . " COLLATE $collation",
            'datetime' => 'DATETIME' . ($nn ? ' NOT NULL' : '') . $d,
            'timestamp'=> 'TIMESTAMP' . ($nn ? ' NOT NULL' : '') . $d,
            'enum'     => "ENUM('" . implode("','", $col['vals']) . "')" . ($nn ? ' NOT NULL' : '') . $d . " COLLATE $collation",
            default    => throw new \RuntimeException('Unknown MySQL type: ' . $col['type']),
        };

        return $type . $cmt;
    }

    // ─── PostgreSQL ──────────────────────────────────────────────────────────

    private static function ddlPgsql(string $table, array $def): array {
        $lines = [];

        foreach ($def['columns'] as $name => $col) {
            if ($col['type'] === 'serial') {
                $lines[] = '  "' . $name . '" SERIAL PRIMARY KEY';
                continue;
            }
            $lines[] = '  "' . $name . '" ' . self::pgsqlColType($name, $col);
        }

        // PK if not serial
        if (!isset($def['columns']['id']) || $def['columns']['id']['type'] !== 'serial') {
            $lines[] = '  PRIMARY KEY ("id")';
        }

        // UNIQUE
        if (!empty($def['unique'])) {
            foreach ($def['unique'] as $u) {
                $cols = is_array($u) ? $u : [$u];
                $lines[] = '  UNIQUE ("' . implode('","', $cols) . '")';
            }
        }

        $statements = [];
        $statements[] = "CREATE TABLE IF NOT EXISTS \"$table\" (\n" . implode(",\n", $lines) . "\n);";

        // INDEX
        if (!empty($def['index'])) {
            foreach ($def['index'] as $ix) {
                $cols = is_array($ix) ? $ix : [$ix];
                $name = 'idx_' . $table . '_' . implode('_', $cols);
                $statements[] = 'CREATE INDEX IF NOT EXISTS "' . $name . '" ON "' . $table . '" ("' . implode('","', $cols) . '");';
            }
        }

        // FK
        if (!empty($def['fk'])) {
            foreach ($def['fk'] as $fk) {
                $statements[] = 'ALTER TABLE "' . $table . '" ADD CONSTRAINT "fk_' . $table . '_' . $fk[0] . '" FOREIGN KEY ("' . $fk[0] . '") REFERENCES "' . $fk[1] . '" ("' . $fk[2] . '");';
            }
        }

        // COMMENTS
        if (self::$options['show_comments'] ?? true) {
            foreach ($def['columns'] as $name => $col) {
                if (isset($col['comment'])) {
                    $statements[] = 'COMMENT ON COLUMN "' . $table . '"."' . $name . '" IS \'' . addslashes($col['comment']) . '\';';
                }
            }
        }

        return $statements;
    }

    private static function pgsqlColType(string $name, array $col): string {
        $nn = $col['notnull'] ?? false;
        $hasD = array_key_exists('default', $col);
        $d = $hasD ? " DEFAULT '" . $col['default'] . "'" : ($nn ? '' : ' DEFAULT NULL');

        if ($col['type'] === 'enum') {
            return "VARCHAR(1) NOT NULL DEFAULT '" . ($col['default'] ?? $col['vals'][0]) . "' CHECK (\"$name\" IN ('" . implode("','", $col['vals']) . "'))";
        }

        return match ($col['type']) {
            'int'      => 'INT' . ($nn ? ' NOT NULL' : '') . $d,
            'bigint'   => 'BIGINT' . ($nn ? ' NOT NULL' : '') . $d,
            'smallint' => 'SMALLINT' . ($nn ? ' NOT NULL' : '') . $d,
            'tinyint'  => 'SMALLINT' . ($nn ? ' NOT NULL' : '') . $d,
            'varchar'  => 'VARCHAR(' . $col['length'] . ')' . ($nn ? ' NOT NULL' : '') . $d,
            'text'     => 'TEXT' . ($nn ? ' NOT NULL' : '') . $d,
            'longtext' => 'TEXT' . ($nn ? ' NOT NULL' : ''),
            'datetime' => 'TIMESTAMP(0)' . ($nn ? ' NOT NULL' : '') . $d,
            'timestamp'=> 'TIMESTAMP(0)' . ($nn ? ' NOT NULL' : '') . $d,
            default    => throw new \RuntimeException('Unknown PostgreSQL type: ' . $col['type']),
        };
    }
}
