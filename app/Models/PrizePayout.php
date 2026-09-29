<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrizePayout extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'draw_result_id',
        'intake_request_id',
        'customer_id',
        'number',
        'bet_amount',
        'prize_amount',
        'reventado_amount',
        'reventado_prize',
        'total_prize',
        'status',
        'paid_by',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'bet_amount' => 'decimal:2',
            'prize_amount' => 'decimal:2',
            'reventado_amount' => 'decimal:2',
            'reventado_prize' => 'decimal:2',
            'total_prize' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function drawResult(): BelongsTo
    {
        return $this->belongsTo(DrawResult::class);
    }

    public function intakeRequest(): BelongsTo
    {
        return $this->belongsTo(IntakeRequest::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
