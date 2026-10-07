<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReserveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canOperateHotel((int) $this->input('hotel_id'));
    }

    public function rules(): array
    {
        $maxNights = (int) config('hotel.max_nights', 60);

        return [
            'hotel_id' => ['required', 'integer', 'exists:hotels,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => [
                'required', 'date_format:Y-m-d', 'after:check_in',
                function (string $attribute, mixed $value, \Closure $fail) use ($maxNights) {
                    $in = date_create_from_format('!Y-m-d', (string) $this->input('check_in'));
                    $out = date_create_from_format('!Y-m-d', (string) $value);
                    if ($in && $out && $in->diff($out)->days > $maxNights) {
                        $fail("A estadia máxima é de {$maxNights} noites.");
                    }
                },
            ],
            'coupon_code' => ['nullable', 'string', 'max:50'],

            'guests' => ['required', 'array', 'min:1', 'max:10'],
            'guests.*.name' => ['required', 'string', 'max:255'],
            'guests.*.last_name' => ['nullable', 'string', 'max:255'],
            'guests.*.phone' => ['nullable', 'string', 'max:30'],

            'dailies' => ['required', 'array', 'min:1', 'max:'.$maxNights],
            'dailies.*.date' => ['required', 'date_format:Y-m-d'],
            'dailies.*.value' => ['required', 'numeric', 'min:0', 'max:99999999.99'],

            'payments' => ['sometimes', 'array', 'max:10'],
            'payments.*.method' => ['required', 'integer', 'min:0', 'max:65535'],
            'payments.*.value' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
        ];
    }
}
