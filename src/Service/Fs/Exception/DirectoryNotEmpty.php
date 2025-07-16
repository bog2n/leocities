<?php

namespace App\Service\Fs\Exception;

class DirectoryNotEmpty extends \Exception {
    public function __construct($code = 0, ?Throwable $previous = null) {
        parent::__construct("Directory is not empty", $code, $previous);
    }
}

