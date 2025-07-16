<?php

namespace App\Service\Fs\Exception;

class NotEnoughSpaceException extends \Exception {
    public function __construct($code = 0, ?Throwable $previous = null) {
        parent::__construct("No space left on block file", $code, $previous);
    }
}

