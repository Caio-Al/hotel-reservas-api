<?php

namespace App\Models\Concerns;

use App\Models\Hotel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasHotelRole
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_RECEPTIONIST = 'receptionist';

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    /** Pode cadastrar e alterar dados do hotel (quartos): admin ou gerente do próprio hotel. */
    public function canManageHotel(int $hotelId): bool
    {
        return $this->isAdmin() || ($this->isManager() && (int) $this->hotel_id === $hotelId);
    }

    /** Pode operar reservas e pagamentos do hotel: admin ou qualquer funcionário do próprio hotel. */
    public function canOperateHotel(int $hotelId): bool
    {
        return $this->isAdmin() || (int) $this->hotel_id === $hotelId;
    }
}
