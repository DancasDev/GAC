<?php

namespace DancasDev\GAC\Restrictions;

use DancasDev\GAC\Restrictions\RestrictionHandlerInterface;
use DancasDev\GAC\Restrictions\Handlers\ByDate;
use DancasDev\GAC\Restrictions\Handlers\ByIp;

class Restrictions {
    protected array $data = [];
    protected array $handlers = [];

    protected static array $handlerMap = [
        'date' => ByDate::class,
        'ip'   => ByIp::class,
    ];

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function getList(): array {
        return $this->data;
    }

    public function setList(array $data): static {
        $this->data = $data;
        return $this;
    }

    public function has(string $type): bool {
        return array_key_exists($type, $this->data);
    }

    /** Early exit — first failing restriction stops */
    public function run(array $context): RestrictionResult {
        foreach ($this->data as $type => $rules) {
            if (!array_key_exists($type, $context) || !is_array($context[$type])) {
                continue;
            }

            $handler = $this->getHandler($type);
            if ($handler === null) {
                continue;
            }

            $result = $handler->validate($rules, $context[$type]);
            if (!$result->passed) {
                return $result;
            }
        }

        return new RestrictionResult(true);
    }

    /** Collect all results (passed and failed) */
    public function runAll(array $context): array {
        $results = [];

        foreach ($this->data as $type => $rules) {
            if (!array_key_exists($type, $context) || !is_array($context[$type])) {
                continue;
            }

            $handler = $this->getHandler($type);
            if ($handler === null) {
                continue;
            }

            $results[] = $handler->validate($rules, $context[$type]);
        }

        return $results;
    }

    /** Validate data structure for a given type + rule */
    public static function validateStructure(string $type, string $rule, array $data): bool {
        $class = self::$handlerMap[$type] ?? null;
        if ($class === null) {
            return false;
        }

        return $class::structureIsValid($rule, $data);
    }

    public static function register(string $alias, string $className): void {
        if (!is_subclass_of($className, RestrictionHandlerInterface::class)) {
            throw new \InvalidArgumentException(
                'The class "' . $className . '" must implement RestrictionHandlerInterface.',
                1,
            );
        }
        self::$handlerMap[$alias] = $className;
    }

    protected function getHandler(string $type): ?RestrictionHandlerInterface {
        if (isset($this->handlers[$type])) {
            return $this->handlers[$type];
        }

        $class = self::$handlerMap[$type] ?? null;
        if ($class === null) {
            return null;
        }

        $this->handlers[$type] = new $class();
        return $this->handlers[$type];
    }
}
