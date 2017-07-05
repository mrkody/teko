<?php

namespace Mrkody\Teko\Exception;

class InvalidOrderException extends PaymentException {
    public function __construct($message = '', $code = 0, \Exception $previous = null) {
        parent::__construct('Invalid type of Order cls.', $code, $previous);
    }
}
