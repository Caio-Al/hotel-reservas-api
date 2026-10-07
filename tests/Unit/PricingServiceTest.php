<?php

namespace Tests\Unit;

use App\Models\Coupon;
use App\Services\PricingService;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    /** @param  array<int, int|float>  $values */
    private function dailies(array $values): array
    {
        $dailies = [];
        foreach (array_values($values) as $i => $value) {
            $dailies[] = ['date' => '2026-12-0'.($i + 1), 'value' => $value];
        }

        return $dailies;
    }

    private function coupon(string $type, float $value): Coupon
    {
        return new Coupon(['type' => $type, 'value' => $value]);
    }

    public function test_sem_cupom_e_sem_taxa_o_total_e_a_soma_das_diarias(): void
    {
        $prices = (new PricingService(0.0))->calculate($this->dailies([100, 100]));

        $this->assertSame(
            ['subtotal' => '200.00', 'discount' => '0.00', 'service_fee' => '0.00', 'total' => '200.00'],
            $prices
        );
    }

    public function test_taxa_de_servico_incide_sobre_o_subtotal(): void
    {
        $prices = (new PricingService(10.0))->calculate($this->dailies([100, 100]));

        $this->assertSame('20.00', $prices['service_fee']);
        $this->assertSame('220.00', $prices['total']);
    }

    public function test_cupom_percentual_desconta_antes_da_taxa(): void
    {
        $prices = (new PricingService(10.0))->calculate($this->dailies([100, 100]), $this->coupon('percent', 10));

        $this->assertSame('200.00', $prices['subtotal']);
        $this->assertSame('20.00', $prices['discount']);
        $this->assertSame('18.00', $prices['service_fee']);
        $this->assertSame('198.00', $prices['total']);
    }

    public function test_cupom_de_valor_fixo(): void
    {
        $prices = (new PricingService(0.0))->calculate($this->dailies([100, 100]), $this->coupon('fixed', 30));

        $this->assertSame('30.00', $prices['discount']);
        $this->assertSame('170.00', $prices['total']);
    }

    public function test_desconto_nunca_passa_do_subtotal(): void
    {
        $prices = (new PricingService(10.0))->calculate($this->dailies([100, 100]), $this->coupon('fixed', 500));

        $this->assertSame('200.00', $prices['discount']);
        $this->assertSame('0.00', $prices['service_fee']);
        $this->assertSame('0.00', $prices['total']);
    }

    public function test_arredonda_em_centavos_sem_erro_de_ponto_flutuante(): void
    {
        $prices = (new PricingService(10.0))->calculate($this->dailies([33.33, 33.33, 33.33]));

        $this->assertSame('99.99', $prices['subtotal']);
        $this->assertSame('10.00', $prices['service_fee']);
        $this->assertSame('109.99', $prices['total']);
    }

    public function test_usa_a_taxa_da_configuracao_quando_nao_informada(): void
    {
        config(['hotel.service_fee_percent' => 5.0]);

        $prices = (new PricingService())->calculate($this->dailies([100, 100]));

        $this->assertSame('10.00', $prices['service_fee']);
        $this->assertSame('210.00', $prices['total']);
    }
}
