<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A sign-in attempt that did not succeed. The message is safe to show on the login page;
 * $countsAsAttempt says whether it moves the account towards lockout (an unreachable API does not —
 * the user never got a real try).
 */
class LoginFailed extends RuntimeException
{
    public function __construct(string $message, public readonly bool $countsAsAttempt = true, public readonly string $field = 'email')
    {
        parent::__construct($message);
    }
}
