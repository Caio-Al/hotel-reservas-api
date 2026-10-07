<?php

namespace App\Services;

use App\Exceptions\PaymentExceedsBalanceException;
use App\Models\Payment;
use App\Models\Reserve;
use App\Repositories\Contracts\ReserveRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(private ReserveRepositoryInterface $reserves)
    {
    }

    public function list(Reserve $reserve): Collection
    {
        return $reserve->payments()->orderBy('id')->get();
    }

    /** Registra um pagamento sem deixar o total pago passar do total da reserva. */
    public function register(Reserve $reserve, array $data): Payment
    {
        return DB::transaction(function () use ($reserve, $data) {
            $locked = $this->reserves->findForUpdate($reserve->id);

            $balance = $this->cents($locked->total) - $this->reserves->paidCents($locked);

            if ($this->cents($data['value']) > $balance) {
                throw new PaymentExceedsBalanceException(
                    'O valor excede o saldo da reserva ('.number_format($balance / 100, 2, '.', '').').'
                );
            }

            $payment = $this->reserves->addPayment($locked, [
                'method' => (int) $data['method'],
                'value' => $data['value'],
            ]);

            Log::info('Pagamento registrado', [
                'reserve_id' => $locked->id,
                'payment_id' => $payment->id,
                'valor' => $data['value'],
            ]);

            return $payment;
        });
    }

    private function cents(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }
}
