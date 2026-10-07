<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pay', $this->route('reserve'));
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'integer', 'min:0', 'max:65535'],
            'value' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
        ];
    }
}
