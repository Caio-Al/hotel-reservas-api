<?php

namespace App\Exceptions;

class InvalidCouponException extends BusinessRuleException
{
    protected int $status = 422;

    public function __construct(string $message = 'Cupom inválido.')
    {
        parent::__construct($message);
    }
}
