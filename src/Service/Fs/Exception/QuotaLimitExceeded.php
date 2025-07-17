<?php

namespace App\Service\Fs\Exception;

class QuotaLimitExceeded extends \Exception {
    public function __construct($code = 0, ?Throwable $previous = null)
    {
        parent::__construct("Quota limit exceeded", $code, $previous);
    }
}

