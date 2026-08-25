<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $guarded = [];
    protected function casts(): array
    {
        return [
            'total_amount'=>'decimal:2','menu_amount'=>'decimal:2','deposit_amount'=>'decimal:2',
            'coupon_discount_amount'=>'decimal:2','point_used'=>'integer','final_amount'=>'decimal:2',
            'paid_at'=>'datetime','preparing_at'=>'datetime','ready_at'=>'datetime',
            'completed_at'=>'datetime','cancelled_at'=>'datetime','refunded_at'=>'datetime',
        ];
    }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    public function payment(): HasOne { return $this->hasOne(Payment::class); }
    public function usedCoupon(): HasOne { return $this->hasOne(UserCoupon::class, 'used_order_id'); }
    public function review(): HasOne { return $this->hasOne(Review::class); }
}
