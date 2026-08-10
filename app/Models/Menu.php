<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Menu extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected function casts(): array { return ['price' => 'decimal:2', 'is_available' => 'boolean']; }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function category(): BelongsTo { return $this->belongsTo(MenuCategory::class, 'category_id'); }
    public function orderItems(): HasMany { return $this->hasMany(OrderItem::class); }
    public function inventories(): BelongsToMany { return $this->belongsToMany(Inventory::class, 'menu_ingredients')->withPivot('required_quantity')->withTimestamps(); }
}