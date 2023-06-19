<?php

namespace Morscate\LaravelSendcloud\Exceptions;

use Exception;

class ValidationException extends Exception
{
    public function __construct(
        public array $missingKeys
    ) {
        parent::__construct('The given data was invalid.');
    }
}
