<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationSeat extends Model
{
    public $timestamps = false;
    const UPDATED_AT = null;
    protected $guarded = [];
    protected function casts(): array { return ['created_at'=>'datetime']; }
    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function slot(): BelongsTo { return $this->belongsTo(ReservationSlot::class, 'reservation_slot_id'); }
    public function seat(): BelongsTo { return $this->belongsTo(StoreSeat::class, 'seat_id'); }
}