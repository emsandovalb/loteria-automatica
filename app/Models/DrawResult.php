<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DrawResult extends Model
{
    protected $fillable = [
        'organization_id',
        'draw_id',
        'draw_date',
        'winning_number',
        'reventado_hit',
        'prize_multiplier',
        'reventado_multiplier',
        'entered_by',
        'winners_notified_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'draw_date' => 'date',
            'reventado_hit' => 'boolean',
            'prize_multiplier' => 'decimal:2',
            'reventado_multiplier' => 'decimal:2',
            'winners_notified_at' => 'datetime',
        ];
    }

    public function draw(): BelongsTo
    {
        return $this->belongsTo(Draw::class);
    }

    public function enteredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(PrizePayout::class);
    }

    /**
     * A result can be corrected until winners were told or any prize was paid.
     */
    public function isLocked(): bool
    {
        return $this->winners_notified_at !== null
            || $this->payouts()->where('status', PrizePayout::STATUS_PAID)->exists();
    }
}
