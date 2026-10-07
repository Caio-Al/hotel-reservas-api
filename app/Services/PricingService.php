<?php

namespace App\Services;

use App\Models\Coupon;

/**
 * Calcula o preço da reserva. Regra:
 *   subtotal = soma das diárias
 *   desconto = cupom (percentual ou valor fixo, limitado ao subtotal)
 *   taxa     = taxa de serviço (%) sobre (subtotal - desconto)
 *   total    = subtotal - desconto + taxa
 * Tudo em centavos inteiros para não ter erro de arredondamento de ponto flutuante.
 */
class PricingService
{
    private float $feePercent;

    public function __construct(?float $feePercent = null)
    {
        $this->feePercent = $feePercent ?? (float) config('hotel.service_fee_percent', 0);
    }

    /**
     * @param  array<int, array{date: string, value: mixed}>  $dailies
     * @return array{subtotal: string, discount: string, service_fee: string, total: string}
     */
    public function calculate(array $dailies, ?Coupon $coupon = null): array
    {
        $subtotal = array_sum(array_map(fn (array $daily) => $this->toCents($daily['value']), $dailies));

        $discount = 0;
        if ($coupon) {
            $discount = $coupon->type === Coupon::TYPE_PERCENT
                ? (int) round($subtotal * ((float) $coupon->value) / 100)
                : $this->toCents($coupon->value);
            $discount = min($discount, $subtotal);
        }

        $base = $subtotal - $discount;
        $fee = (int) round($base * $this->feePercent / 100);

        return [
            'subtotal' => $this->fromCents($subtotal),
            'discount' => $this->fromCents($discount),
            'service_fee' => $this->fromCents($fee),
            'total' => $this->fromCents($base + $fee),
        ];
    }

    private function toCents(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    private function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
