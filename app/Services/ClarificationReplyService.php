<?php

namespace App\Services;

use App\Models\IncomingMessage;
use App\Models\IntakeRequest;
use App\Models\IntakeRequestEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Links a customer's answer to the request an operator asked them about, and pre-fills
 * only the fields that are still missing. The operator always confirms afterwards.
 */
class ClarificationReplyService
{
    public function __construct(
        private readonly MessageParserService $messageParserService,
        private readonly IntakeMessageService $intakeMessageService,
    ) {
    }

    public function openRequestFor(string $channelType, string $fromIdentifier): ?IntakeRequest
    {
        return IntakeRequest::query()
            // Replies after the window are treated as new orders instead of answers.
            ->where('awaiting_reply_since', '>=', now()->subHours(max(1, (int) config('services.customer_replies.window_hours', 6))))
            ->whereIn('status', [IntakeRequest::STATUS_PENDING, IntakeRequest::STATUS_NEEDS_REVIEW])
            ->whereHas('incomingMessage', fn ($query) => $query
                ->where('channel_type', $channelType)
                ->where('from_identifier', $fromIdentifier))
            ->latest('awaiting_reply_since')
            ->first();
    }

    /**
     * @return array<string, mixed> the fields that were filled from the reply
     */
    public function recordReply(
        IntakeRequest $request,
        string $text,
        ?array $payload = null,
        ?string $externalMessageId = null,
        ?string $toIdentifier = null,
    ): array {
        return DB::transaction(function () use ($request, $text, $payload, $externalMessageId, $toIdentifier): array {
            $original = $request->incomingMessage;

            IncomingMessage::create([
                'organization_id' => $request->organization_id,
                'branch_id' => $request->branch_id,
                'customer_id' => $request->customer_id,
                'channel_type' => $original->channel_type,
                'from_identifier' => $original->from_identifier,
                'to_identifier' => $toIdentifier ?? $original->to_identifier,
                'raw_text' => $text,
                'payload_json' => array_filter([
                    'source' => $original->channel_type,
                    'reply_to_request_id' => $request->id,
                    'payload' => $payload,
                ], fn ($value) => $value !== null),
                'external_message_id' => $externalMessageId,
                'status' => IncomingMessage::STATUS_PROCESSED,
                'received_at' => now(),
            ]);

            $filled = $this->fieldsFromReply($request, $text);

            $request->update(array_merge($filled, [
                'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
                'awaiting_reply_since' => null,
                'notes' => trim(implode("\n", array_filter([
                    $request->notes,
                    __('Customer replied: ":text"', ['text' => $text]),
                ]))),
            ]));

            $request->events()->create([
                'user_id' => null,
                'event_type' => IntakeRequestEvent::EVENT_CUSTOMER_REPLIED,
                'old_values' => null,
                'new_values' => $filled ?: null,
                'notes' => __('Customer replied: ":text"', ['text' => $text]),
                'created_at' => now(),
            ]);

            return $filled;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldsFromReply(IntakeRequest $request, string $text): array
    {
        $parsed = $this->messageParserService->parse($text);
        $normalized = Str::ascii(mb_strtolower(trim($text)));
        $normalized = preg_replace('/(?<=\d)[,.](?=\d)/u', '', $normalized) ?? $normalized;
        $candidates = [];

        if (count($parsed['items']) === 1) {
            $candidates['detected_number'] = $parsed['items'][0]['detected_number'];
            $candidates['detected_amount'] = $parsed['items'][0]['detected_amount'];
        } elseif ($request->detected_number === null && preg_match('/^(?:el|al|numero|num|#)?\s*(\d{1,2})$/u', $normalized, $matches) === 1) {
            // "28" or "el 28" answers "which number?"
            $candidates['detected_number'] = str_pad((string) (int) $matches[1], 2, '0', STR_PAD_LEFT);
        } elseif ($request->detected_amount === null && preg_match('/^\D{0,3}(\d+)\s*(mil)?(?:\s*colones)?$/u', $normalized, $matches) === 1) {
            // "1000", "5 mil" or "₡1000" answers "how much?"
            $candidates['detected_amount'] = (int) $matches[1] * (isset($matches[2]) && $matches[2] !== '' ? 1000 : 1);
        }

        // A bare "12" answering "which number?" must not also be read as the 12:00 md draw.
        $isBareValueReply = count($parsed['items']) !== 1 && $candidates !== [];

        if ($request->draw_id === null && ! $isBareValueReply) {
            $draw = $this->intakeMessageService->resolveDrawForOrganization($request->organization_id, $parsed['draw_reference']);

            if ($draw !== null) {
                $candidates['draw_id'] = $draw->id;
            }
        }

        // Never overwrite what the operator or the original message already set.
        return array_filter(
            $candidates,
            fn ($value, string $field) => $value !== null && $request->{$field} === null,
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
