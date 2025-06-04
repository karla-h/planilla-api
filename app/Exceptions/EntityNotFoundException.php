<?php

namespace App\Exceptions;

use Exception;

class EntityNotFoundException extends Exception
{
    public function __construct($message = "Entity not found", $code = 404)
    {
        parent::__construct($message, $code);
    }
}
