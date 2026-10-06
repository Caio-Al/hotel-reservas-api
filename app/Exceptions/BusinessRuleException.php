<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

abstract class BusinessRuleException extends RuntimeException
{
    protected int $status = 422;

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->status);
    }

    public function report(): bool
    {
        Log::warning('Regra de negócio violada', [
            'exception' => static::class,
            'mensagem' => $this->getMessage(),
        ]);

        return true;
    }
}
