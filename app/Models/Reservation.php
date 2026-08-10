<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    protected $guarded = [];
    protected function casts(): array
    {
        return [
            'guest_count'=>'integer','approved_at'=>'datetime','rejected_at'=>'datetime',
            'approval_expires_at'=>'datetime','payment_expires_at'=>'datetime',
            'confirmed_at'=>'datetime','cancelled_at'=>'datetime','completed_at'=>'datetime',
        ];
    }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function slot(): BelongsTo { return $this->belongsTo(ReservationSlot::class, 'reservation_slot_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function reservationSeats(): HasMany { return $this->hasMany(ReservationSeat::class); }
    public function seats(): BelongsToMany { return $this->belongsToMany(StoreSeat::class, 'reservation_seats', 'reservation_id', 'seat_id'); }
    public function order(): HasOne { return $this->hasOne(Order::class); }
}