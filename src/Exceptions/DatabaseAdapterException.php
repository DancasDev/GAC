<?php

namespace DancasDev\GAC\Exceptions;

use Exception;

class DatabaseAdapterException extends Exception {
    public function __construct($message, $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}