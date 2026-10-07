<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->user('admin@hotel.test', 'Administrador', User::ROLE_ADMIN, null);

        if (Hotel::find(1)) {
            $this->user('gerente@hotel.test', 'Gerente Hotel 1', User::ROLE_MANAGER, 1);
            $this->user('recepcao@hotel.test', 'Recepção Hotel 1', User::ROLE_RECEPTIONIST, 1);
        }

        Coupon::updateOrCreate(['code' => 'BEMVINDO10'], [
            'type' => Coupon::TYPE_PERCENT,
            'value' => 10,
            'active' => true,
        ]);
    }

    private function user(string $email, string $name, string $role, ?int $hotelId): void
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => Hash::make('password'),
            'role' => $role,
            'hotel_id' => $hotelId,
        ])->save();
    }
}
