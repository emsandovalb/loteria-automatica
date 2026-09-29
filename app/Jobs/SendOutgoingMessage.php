<?php

namespace App\Jobs;

use App\Models\Branch;
use App\Models\OutgoingMessage;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendOutgoingMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $outgoingMessageId,
    ) {
    }

    public function handle(TelegramBotService $telegramBotService): void
    {
        $message = OutgoingMessage::query()->find($this->outgoingMessageId);

        if (! $message || $message->status !== OutgoingMessage::STATUS_PENDING) {
            return;
        }

        if ($message->channel_type !== Branch::CHANNEL_TYPE_TELEGRAM) {
            $message->update([
                'status' => OutgoingMessage::STATUS_NOT_SENT,
                'error' => sprintf('Channel "%s" cannot receive outbound messages yet.', $message->channel_type),
            ]);

            return;
        }

        try {
            $response = $telegramBotService->sendMessage($message->to_identifier, $message->text);

            $message->update([
                'status' => OutgoingMessage::STATUS_SENT,
                'external_message_id' => isset($response['result']['message_id']) ? (string) $response['result']['message_id'] : null,
                'sent_at' => now(),
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            // Never break the operator's action because the customer could not be reached;
            // the failure stays visible on the request detail page.
            Log::warning('Outgoing customer message failed.', [
                'outgoing_message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            $message->update([
                'status' => OutgoingMessage::STATUS_FAILED,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
