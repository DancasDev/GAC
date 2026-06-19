<?php

namespace DancasDev\GAC\Restrictions\Handlers;

use DancasDev\GAC\Restrictions\RestrictionResult;
use DancasDev\GAC\Restrictions\RestrictionHandlerInterface;

class ByDate implements RestrictionHandlerInterface {
    protected array $methodMap = [
        'before'    => 'before',
        'in_range'  => 'inRange',
        'out_range' => 'outRange',
        'after'     => 'after',
    ];

    public function validate(array $rules, array $context): RestrictionResult {
        foreach ($rules as $rule) {
            $method = $this->methodMap[$rule['r']] ?? null;
            if ($method === null || !method_exists($this, $method)) {
                continue;
            }

            $result = $this->{$method}($rule, $context);
            if (!$result->passed) {
                return $result;
            }
        }

        return new RestrictionResult(true);
    }

    public static function structureIsValid(string $rule, array $data): bool {
        return match ($rule) {
            'before', 'after'      => isset($data['d']) && is_string($data['d']),
            'in_range', 'out_range' => isset($data['sd'], $data['ed']) && is_string($data['sd']) && is_string($data['ed']),
            default                => false,
        };
    }

    protected function before(array $rule, array $context): RestrictionResult {
        $d = $this->resolveDate($rule['c']['d']);
        if ($d === false) {
            return $this->fail($rule, $d, $context, 'Invalid date format');
        }

            if (!isset($context['timestamp']) || !is_int($context['timestamp'])) {
            return $this->fail($rule, $d, $context, 'Missing or invalid timestamp');
        }

        if ($context['timestamp'] >= $d) {
            return $this->fail($rule, $d, $context, 'Date is after the allowed deadline');
        }

        return new RestrictionResult(true);
    }

    protected function inRange(array $rule, array $context): RestrictionResult {
        $sd = $this->resolveDate($rule['c']['sd']);
        $ed = $this->resolveDate($rule['c']['ed']);
        if ($sd === false || $ed === false) {
            return $this->fail($rule, ['sd' => $sd, 'ed' => $ed], $context, 'Invalid date format');
        }

        if (!isset($context['timestamp']) || !is_int($context['timestamp'])) {
            return $this->fail($rule, ['sd' => $sd, 'ed' => $ed], $context, 'Missing or invalid timestamp');
        }

        if (!($context['timestamp'] >= $sd && $context['timestamp'] <= $ed)) {
            return $this->fail($rule, ['sd' => $sd, 'ed' => $ed], $context, 'Timestamp is outside the allowed range');
        }

        return new RestrictionResult(true);
    }

    protected function outRange(array $rule, array $context): RestrictionResult {
        $sd = $this->resolveDate($rule['c']['sd']);
        $ed = $this->resolveDate($rule['c']['ed']);
        if ($sd === false || $ed === false) {
            return $this->fail($rule, ['sd' => $sd, 'ed' => $ed], $context, 'Invalid date format');
        }

        if (!isset($context['timestamp']) || !is_int($context['timestamp'])) {
            return $this->fail($rule, ['sd' => $sd, 'ed' => $ed], $context, 'Missing or invalid timestamp');
        }

        if ($context['timestamp'] >= $sd && $context['timestamp'] <= $ed) {
            return $this->fail($rule, ['sd' => $sd, 'ed' => $ed], $context, 'Timestamp is within the restricted range');
        }

        return new RestrictionResult(true);
    }

    protected function after(array $rule, array $context): RestrictionResult {
        $d = $this->resolveDate($rule['c']['d']);
        if ($d === false) {
            return $this->fail($rule, $d, $context, 'Invalid date format');
        }

        if (!isset($context['timestamp']) || !is_int($context['timestamp'])) {
            return $this->fail($rule, $d, $context, 'Missing or invalid timestamp');
        }

        if ($context['timestamp'] <= $d) {
            return $this->fail($rule, $d, $context, 'Date is before the allowed start');
        }

        return new RestrictionResult(true);
    }

    protected function resolveDate(string $date): int|false {
        $now = date('Y-m-d');
        $parts = explode('-', $now);
        $date = str_replace('%Y', $parts[0], $date);
        $date = str_replace('%M', $parts[1], $date);
        $date = str_replace('%D', $parts[2], $date);
        return strtotime($date);
    }

    protected function fail(array $rule, mixed $config, array $context, string $msg): RestrictionResult {
        return new RestrictionResult(
            passed: false,
            type: 'date',
            rule: $rule['r'],
            restrictionId: $rule['i'] ?? null,
            ruleConfig: $config,
            context: $context,
            message: $msg,
        );
    }
}
