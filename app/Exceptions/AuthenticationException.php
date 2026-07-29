<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Failed login or invalid credentials. Controllers map this to HTTP 401.
 * Message must stay generic to avoid account enumeration.
 */
class AuthenticationException extends RuntimeException
{
}
