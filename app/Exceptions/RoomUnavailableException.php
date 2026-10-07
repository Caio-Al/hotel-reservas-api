<?php

namespace App\Exceptions;

class RoomUnavailableException extends BusinessRuleException
{
    protected int $status = 409;

    public function __construct(string $message = 'O quarto já está reservado no período informado.')
    {
        parent::__construct($message);
    }
}
