<?php

namespace App\Service\Fs\Exception;

class DirectoryAlreadyExists extends \Exception {
    public function __construct($code = 0, ?Throwable $previous = null)
    {
        parent::__construct("Directory already exists", $code, $previous);
    }
}

