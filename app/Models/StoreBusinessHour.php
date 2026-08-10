<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreBusinessHour extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['is_closed' => 'boolean']; }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
}