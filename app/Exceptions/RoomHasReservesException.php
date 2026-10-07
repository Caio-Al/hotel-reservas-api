<?php

namespace App\Exceptions;

class RoomHasReservesException extends BusinessRuleException
{
    protected int $status = 409;

    public function __construct(string $message = 'O quarto possui reservas e não pode ser removido.')
    {
        parent::__construct($message);
    }
}
