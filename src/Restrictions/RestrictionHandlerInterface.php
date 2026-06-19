<?php

namespace DancasDev\GAC\Restrictions;

interface RestrictionHandlerInterface {
    public function validate(array $rules, array $context): RestrictionResult;

    public static function structureIsValid(string $rule, array $data): bool;
}
