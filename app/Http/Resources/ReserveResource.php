<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ReserveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $paid = $this->payments->sum(fn ($payment) => (int) round(((float) $payment->value) * 100));
        $total = (int) round(((float) $this->total) * 100);

        return [
            'id' => $this->id,
            'hotel_id' => $this->hotel_id,
            'room_id' => $this->room_id,
            'check_in' => Carbon::parse($this->check_in)->toDateString(),
            'check_out' => Carbon::parse($this->check_out)->toDateString(),
            'subtotal' => $this->money($this->subtotal),
            'discount' => $this->money($this->discount),
            'service_fee' => $this->money($this->service_fee),
            'total' => $this->money($this->total),
            'coupon' => $this->coupon?->code,
            'paid' => number_format($paid / 100, 2, '.', ''),
            'balance' => number_format(($total - $paid) / 100, 2, '.', ''),
            'guests' => $this->guests->map(fn ($guest) => [
                'name' => $guest->name,
                'last_name' => $guest->last_name,
                'phone' => $guest->phone,
            ])->values(),
            'dailies' => $this->dailies->map(fn ($daily) => [
                'date' => Carbon::parse($daily->date)->toDateString(),
                'value' => $this->money($daily->value),
            ])->values(),
            'payments' => PaymentResource::collection($this->payments),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
