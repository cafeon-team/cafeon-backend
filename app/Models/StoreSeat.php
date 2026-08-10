<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreSeat extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected function casts(): array { return ['capacity' => 'integer', 'floor_number' => 'integer', 'is_active' => 'boolean']; }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function reservationSeats(): HasMany { return $this->hasMany(ReservationSeat::class, 'seat_id'); }
}