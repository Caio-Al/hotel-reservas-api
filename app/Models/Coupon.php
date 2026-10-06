<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    protected $fillable = [
        'code', 'type', 'value', 'valid_from', 'valid_until', 'max_uses', 'used_count', 'active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'valid_from' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'active' => 'boolean',
        ];
    }
}
