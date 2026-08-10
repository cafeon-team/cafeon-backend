<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreNoshowPolicy extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'deposit_required' => 'boolean',
            'deposit_amount' => 'decimal:2',
            'free_cancellation_minutes' => 'integer',
            'penalty_point_amount' => 'integer',
            'reservation_block_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
