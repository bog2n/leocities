<?php

namespace App\Service\Fs\Exception;

class IsFileException extends \Exception {
    public function __construct($code = 0, ?Throwable $previous = null)
    {
        parent::__construct("Is a file", $code, $previous);
    }
}

