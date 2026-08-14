<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Waitlist extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'queued_on' => 'date',
            'guest_count' => 'integer',
            'queue_number' => 'integer',
            'estimated_wait_minutes' => 'integer',
            'called_at' => 'datetime',
            'seated_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
