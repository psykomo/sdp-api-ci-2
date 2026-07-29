<?php

namespace App\Exceptions;

use DomainException;

/**
 * Field-level validation failure from a Model or service rule check.
 * Controllers map this to HTTP 422 with an `errors` payload.
 */
class ValidationException extends DomainException
{
    /**
     * @param array<string, string|list<string>> $errors
     */
    public function __construct(
        string $message = 'Validation failed.',
        protected array $errors = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
