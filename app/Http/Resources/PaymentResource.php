<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'method' => (int) $this->method,
            'value' => number_format((float) $this->value, 2, '.', ''),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
