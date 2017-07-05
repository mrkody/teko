<?php

namespace Mrkody\Teko\Exception;

class InvalidSignatureException extends PaymentException {
    public function __construct($message = '', $code = 0, \Exception $previous = null) {
        parent::__construct('Invalid signature.', $code, $previous);
    }
}
