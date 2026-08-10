<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCategory extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['sort_order' => 'integer', 'is_active' => 'boolean']; }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function menus(): HasMany { return $this->hasMany(Menu::class, 'category_id'); }
}