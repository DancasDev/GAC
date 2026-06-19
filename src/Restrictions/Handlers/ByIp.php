<?php

namespace DancasDev\GAC\Restrictions\Handlers;

use DancasDev\GAC\Restrictions\RestrictionResult;
use DancasDev\GAC\Restrictions\RestrictionHandlerInterface;

class ByIp implements RestrictionHandlerInterface {
    protected array $methodMap = [
        'allow' => 'allow',
        'deny'  => 'deny',
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
        if (!in_array($rule, ['allow', 'deny'], true)) {
            return false;
        }
        if (!isset($data['list']) || !is_array($data['list'])) {
            return false;
        }
        foreach ($data['list'] as $ip) {
            if (!is_string($ip)) {
                return false;
            }
        }
        return true;
    }

    protected function allow(array $rule, array $context): RestrictionResult {
        $ip = $context['ip'] ?? '';
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return $this->fail($rule, $ip, 'Invalid IP address');
        }

        $list = $rule['c']['list'] ?? [];
        foreach ($list as $pattern) {
            if ($this->ipMatches($ip, $pattern)) {
                return new RestrictionResult(true);
            }
        }

        return $this->fail($rule, $ip, 'IP not in whitelist');
    }

    protected function deny(array $rule, array $context): RestrictionResult {
        $ip = $context['ip'] ?? '';
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return $this->fail($rule, $ip, 'Invalid IP address');
        }

        $list = $rule['c']['list'] ?? [];
        foreach ($list as $pattern) {
            if ($this->ipMatches($ip, $pattern)) {
                return $this->fail($rule, $ip, 'IP in blacklist');
            }
        }

        return new RestrictionResult(true);
    }

    protected function ipMatches(string $ip, string $pattern): bool {
        if ($pattern === $ip) {
            return true;
        }
        if (!str_contains($pattern, '*')) {
            return false;
        }

        $regex = '/^' . str_replace(['.', '*'], ['\.', '\d+'], $pattern) . '$/';
        return (bool) preg_match($regex, $ip);
    }

    protected function fail(array $rule, string $ip, string $msg): RestrictionResult {
        return new RestrictionResult(
            passed: false,
            type: 'ip',
            rule: $rule['r'],
            restrictionId: $rule['i'] ?? null,
            ruleConfig: $rule['c'] ?? [],
            context: ['ip' => $ip],
            message: $msg,
        );
    }
}
