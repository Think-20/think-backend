<?php

namespace App;

use Exception;

class SerproAuthException extends Exception
{
    /** @var int */
    public $statusCode;

    public function __construct($message, $statusCode = 401)
    {
        parent::__construct($message, (int) $statusCode);
        $this->statusCode = (int) $statusCode;
    }
}
