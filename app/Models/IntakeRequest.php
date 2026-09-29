<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IntakeRequest extends Model
{
    use HasFactory;

    protected $table = 'requests';

    public const STATUS_PENDING = 'pending';
    public const STATUS_NEEDS_REVIEW = 'needs_review';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';

    public const EVENT_CREATED = 'created';
    public const EVENT_EDITED = 'edited';
    public const EVENT_CONFIRMED = 'confirmed';
    public const EVENT_REJECTED = 'rejected';
    public const EVENT_STATUS_CHANGED = 'status_changed';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'draw_id',
        'draw_date',
        'customer_id',
        'incoming_message_id',
        'detected_number',
        'detected_amount',
        'reventado_amount',
        'raw_text',
        'status',
        'confirmed_by',
        'confirmed_at',
        'rejected_by',
        'rejected_at',
        'notes',
        'awaiting_reply_since',
    ];

    protected function casts(): array
    {
        return [
            'detected_amount' => 'decimal:2',
            'reventado_amount' => 'decimal:2',
            'draw_date' => 'date',
            'confirmed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'awaiting_reply_since' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (IntakeRequest $request): void {
            if ($request->draw_date !== null) {
                return;
            }

            $moment = $request->created_at ?? now();
            $draw = $request->draw_id ? Draw::query()->find($request->draw_id) : null;

            $request->draw_date = $draw?->operatingDate($moment)
                ?? $moment->copy()->setTimezone(config('app.timezone'))->toDateString();
        });
    }

    /**
     * Fields that must be filled before the request can be confirmed.
     *
     * @return array<int, string>
     */
    public function missingConfirmationFields(): array
    {
        return array_keys(array_filter([
            'number' => $this->detected_number === null || $this->detected_number === '',
            'amount' => $this->detected_amount === null || (float) $this->detected_amount <= 0,
            'draw' => $this->draw_id === null,
        ]));
    }

    public function isReadyForConfirmation(): bool
    {
        return $this->missingConfirmationFields() === [];
    }

    /**
     * A request belongs to a closed day once its branch has a daily closure for its draw date.
     */
    public function isInClosedDay(): bool
    {
        if ($this->draw_date === null) {
            return false;
        }

        return BranchDailyClosure::query()
            ->where('branch_id', $this->branch_id)
            ->whereDate('closure_date', $this->draw_date->toDateString())
            ->exists();
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function incomingMessage(): BelongsTo
    {
        return $this->belongsTo(IncomingMessage::class);
    }

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(IntakeRequestEvent::class);
    }

    public function payout(): HasOne
    {
        return $this->hasOne(PrizePayout::class);
    }

    /**
     * Once the winning number is known, the request can no longer be confirmed or edited.
     */
    public function hasDrawResult(): bool
    {
        if ($this->draw_id === null || $this->draw_date === null) {
            return false;
        }

        return DrawResult::query()
            ->where('draw_id', $this->draw_id)
            ->whereDate('draw_date', $this->draw_date->toDateString())
            ->exists();
    }

    public function outgoingMessages(): HasMany
    {
        return $this->hasMany(OutgoingMessage::class);
    }

    public function isAwaitingCustomerReply(): bool
    {
        return $this->awaiting_reply_since !== null;
    }
}
