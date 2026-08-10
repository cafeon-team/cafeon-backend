<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    public $timestamps = false;
    const UPDATED_AT = null;
    protected $guarded = [];
    protected function casts(): array { return ['unit_price'=>'decimal:2','quantity'=>'integer','line_amount'=>'decimal:2','created_at'=>'datetime']; }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function menu(): BelongsTo { return $this->belongsTo(Menu::class); }
}