<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreImage extends Model
{
    public $timestamps = false;
    const UPDATED_AT = null;
    protected $guarded = [];
    protected function casts(): array { return ['created_at' => 'datetime']; }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
}