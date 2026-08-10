<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['amount'=>'decimal:2','cancelled_amount'=>'decimal:2','approved_at'=>'datetime','cancelled_at'=>'datetime']; }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}