<?php

namespace App\Service\Fs\Exception;

class FileAlreadyExists extends \Exception {
    public function __construct($code = 0, ?Throwable $previous = null)
    {
        parent::__construct("File already exists", $code, $previous);
    }
}

