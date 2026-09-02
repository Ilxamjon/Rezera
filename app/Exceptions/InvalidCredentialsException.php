<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidCredentialsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('auth.invalid_credentials'));
    }
}
