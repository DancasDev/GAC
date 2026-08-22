<?php

namespace DancasDev\GAC\Restrictions\Handlers;

use DancasDev\GAC\Restrictions\RestrictionResult;
use DancasDev\GAC\Restrictions\RestrictionHandlerInterface;

class ByDomain implements RestrictionHandlerInterface {
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

    public static function structureIsValid(string $rule, array $data): array|false {
        if (!in_array($rule, ['allow', 'deny'], true)) {
            return false;
        }
        if (!isset($data['list']) || !is_array($data['list'])) {
            return false;
        }
        foreach ($data['list'] as $domain) {
            if (!is_string($domain)) {
                return false;
            }
        }
        return ['list' => $data['list']];
    }

    protected function allow(array $rule, array $context): RestrictionResult {
        $host = $context['host'] ?? '';
        if ($host === '' || !is_string($host)) {
            return $this->fail($rule, $host, 'Missing or invalid host');
        }

        $list = $rule['c']['list'] ?? [];
        foreach ($list as $pattern) {
            if ($this->domainMatches($host, $pattern)) {
                return new RestrictionResult(true);
            }
        }

        return $this->fail($rule, $host, 'Host not in whitelist');
    }

    protected function deny(array $rule, array $context): RestrictionResult {
        $host = $context['host'] ?? '';
        if ($host === '' || !is_string($host)) {
            return $this->fail($rule, $host, 'Missing or invalid host');
        }

        $list = $rule['c']['list'] ?? [];
        foreach ($list as $pattern) {
            if ($this->domainMatches($host, $pattern)) {
                return $this->fail($rule, $host, 'Host in blacklist');
            }
        }

        return new RestrictionResult(true);
    }

    protected function domainMatches(string $host, string $pattern): bool {
        if ($pattern === $host) {
            return true;
        }
        if ($pattern === '*') {
            return true;
        }
        if (!str_contains($pattern, '*')) {
            return false;
        }
        $regex = '/^' . str_replace(['.', '*'], ['\.', '.+'], $pattern) . '$/';
        return (bool) preg_match($regex, $host);
    }

    protected function fail(array $rule, string $host, string $msg): RestrictionResult {
        return new RestrictionResult(
            passed: false,
            type: 'domain',
            rule: $rule['r'],
            restrictionId: $rule['i'] ?? null,
            ruleConfig: $rule['c'] ?? [],
            context: ['host' => $host],
            message: $msg,
        );
    }
}
