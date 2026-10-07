<?php

namespace App\Exceptions;

class PaymentExceedsBalanceException extends BusinessRuleException
{
    protected int $status = 422;

    public function __construct(string $message = 'O valor excede o saldo da reserva.')
    {
        parent::__construct($message);
    }
}
