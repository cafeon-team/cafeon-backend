<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReservationSlot extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['slot_date' => 'date', 'is_active' => 'boolean']; }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function reservations(): HasMany { return $this->hasMany(Reservation::class); }
    public function reservationSeats(): HasMany { return $this->hasMany(ReservationSeat::class); }
}