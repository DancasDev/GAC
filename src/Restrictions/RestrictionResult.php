<?php

namespace DancasDev\GAC\Restrictions;

class RestrictionResult implements \JsonSerializable {
    public readonly bool $passed;
    public readonly ?string $type;
    public readonly ?string $rule;
    public readonly ?int $restrictionId;
    public readonly mixed $ruleConfig;
    public readonly mixed $context;
    public readonly ?string $message;

    public function __construct(
        bool $passed,
        ?string $type = null,
        ?string $rule = null,
        ?int $restrictionId = null,
        mixed $ruleConfig = null,
        mixed $context = null,
        ?string $message = null,
    ) {
        $this->passed = $passed;
        $this->type = $type;
        $this->rule = $rule;
        $this->restrictionId = $restrictionId;
        $this->ruleConfig = $ruleConfig;
        $this->context = $context;
        $this->message = $message;
    }

    public function jsonSerialize(): array {
        return [
            'passed'          => $this->passed,
            'type'            => $this->type,
            'rule'            => $this->rule,
            'restriction_id'  => $this->restrictionId,
            'config'          => $this->ruleConfig,
            'context'         => $this->context,
            'message'         => $this->message,
        ];
    }
}
