<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Draw extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'organization_id',
        'name',
        'draw_time',
        'close_time',
        'cutoff_minutes_before',
        'timezone',
        'closes_at_next_day',
        'is_accepting_requests',
        'status',
    ];

    protected $attributes = [
        'cutoff_minutes_before' => 0,
        'timezone' => 'America/Costa_Rica',
        'closes_at_next_day' => false,
        'is_accepting_requests' => true,
    ];

    protected function casts(): array
    {
        return [
            'draw_time' => 'string',
            'close_time' => 'string',
            'cutoff_minutes_before' => 'integer',
            'timezone' => 'string',
            'closes_at_next_day' => 'boolean',
            'is_accepting_requests' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(IntakeRequest::class);
    }

    public function numberLimits(): HasMany
    {
        return $this->hasMany(NumberLimit::class);
    }

    public function isOpenForIntake(?Carbon $now = null): bool
    {
        return $this->closingReason($now) === null;
    }

    public function closingReason(?Carbon $now = null): ?string
    {
        if ($this->status === self::STATUS_INACTIVE) {
            return 'inactive';
        }

        if (! $this->is_accepting_requests) {
            return 'manually_closed';
        }

        $timezone = $this->timezone ?: config('app.timezone', 'UTC');
        $localNow = ($now ?? now())->copy()->setTimezone($timezone);
        $drawAt = $this->timeOnDate($localNow, $this->draw_time);

        if ($this->close_time !== null) {
            $closeAt = $this->timeOnDate($localNow, $this->close_time);

            if ($this->closes_at_next_day && $closeAt->lessThanOrEqualTo($drawAt)) {
                $closeAt->addDay();
            }

            if ($localNow->greaterThan($closeAt)) {
                return 'closed_by_time';
            }
        }

        if (($this->cutoff_minutes_before ?? 0) > 0) {
            $cutoffAt = $drawAt->copy()->subMinutes((int) $this->cutoff_minutes_before);

            if ($localNow->greaterThan($cutoffAt)) {
                return 'closed_by_cutoff';
            }
        }

        return null;
    }

    public function intakeStatusLabel(?Carbon $now = null): string
    {
        return match ($this->closingReason($now)) {
            'inactive' => 'Inactive',
            'manually_closed' => 'Manually closed',
            'closed_by_time' => 'Closed by time',
            'closed_by_cutoff' => 'Closed by cutoff',
            default => 'Open',
        };
    }

    private function timeOnDate(Carbon $reference, string $time): Carbon
    {
        $date = $reference->toDateString();

        return Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $date . ' ' . $time,
            $reference->getTimezone()
        );
    }
}
