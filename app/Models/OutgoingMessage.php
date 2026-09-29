<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutgoingMessage extends Model
{
    public const TYPE_CONFIRMED = 'confirmed';
    public const TYPE_REJECTED = 'rejected';
    public const TYPE_CLARIFICATION = 'clarification';
    public const TYPE_REPLY_RECEIVED = 'reply_received';
    public const TYPE_WINNER = 'winner';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    /** The customer's channel cannot receive outbound messages (e.g. the simulator). */
    public const STATUS_NOT_SENT = 'not_sent';

    protected $fillable = [
        'organization_id',
        'intake_request_id',
        'customer_id',
        'user_id',
        'channel_type',
        'to_identifier',
        'message_type',
        'text',
        'status',
        'error',
        'external_message_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function intakeRequest(): BelongsTo
    {
        return $this->belongsTo(IntakeRequest::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
