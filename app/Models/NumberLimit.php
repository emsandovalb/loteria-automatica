<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NumberLimit extends Model
{
    use HasFactory;

    public const RESTRICTION_TYPE_NORMAL = 'normal';
    public const RESTRICTION_TYPE_RESTRICTED = 'restricted';
    public const RESTRICTION_TYPE_HOT = 'hot';
    public const RESTRICTION_TYPE_BLOCKED = 'blocked';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'draw_id',
        'number',
        'max_amount',
        'is_restricted',
        'restriction_type',
        'restriction_reason',
        'requires_manual_review',
        'is_blocked',
    ];

    protected function casts(): array
    {
        return [
            'max_amount' => 'decimal:2',
            'is_restricted' => 'boolean',
            'requires_manual_review' => 'boolean',
            'is_blocked' => 'boolean',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function restrictionTypes(): array
    {
        return [
            self::RESTRICTION_TYPE_NORMAL,
            self::RESTRICTION_TYPE_RESTRICTED,
            self::RESTRICTION_TYPE_HOT,
            self::RESTRICTION_TYPE_BLOCKED,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function draw(): BelongsTo
    {
        return $this->belongsTo(Draw::class);
    }
}
