<?php

namespace App\Services;

use App\Jobs\SendOutgoingMessage;
use App\Models\IntakeRequest;
use App\Models\OutgoingMessage;
use App\Models\PrizePayout;
use App\Models\User;

/**
 * Sends operator-driven messages (confirmation, rejection, clarification) back to the
 * customer through the channel their original message came from.
 */
class CustomerMessengerService
{
    public function canMessageCustomer(IntakeRequest $request): bool
    {
        return $this->recipientFor($request) !== null;
    }

    public function notifyConfirmed(IntakeRequest $request, ?User $user): ?OutgoingMessage
    {
        return $this->send($request, $user, OutgoingMessage::TYPE_CONFIRMED, implode("\n", [
            '✅ Tu solicitud fue confirmada.',
            '',
            $this->requestLine($request),
            '',
            '¡Mucha suerte!',
        ]));
    }

    public function notifyRejected(IntakeRequest $request, ?User $user, string $reason): ?OutgoingMessage
    {
        return $this->send($request, $user, OutgoingMessage::TYPE_REJECTED, implode("\n", [
            '❌ Tu solicitud no fue aceptada.',
            '',
            $this->requestLine($request),
            '',
            'Motivo: ' . trim($reason),
        ]));
    }

    public function requestClarification(IntakeRequest $request, ?User $user, string $question): ?OutgoingMessage
    {
        return $this->send($request, $user, OutgoingMessage::TYPE_CLARIFICATION, implode("\n", [
            'Sobre tu mensaje:',
            '"' . ($request->incomingMessage?->raw_text ?? $request->raw_text) . '"',
            '',
            trim($question),
            '',
            'Respóndenos por este chat para completar tu solicitud.',
        ]));
    }

    public function notifyWinner(PrizePayout $payout, ?User $user): ?OutgoingMessage
    {
        $request = $payout->intakeRequest;
        $lines = [
            '🎉 ¡Felicidades! Tu número salió ganador.',
            '',
            $this->requestLine($request),
            '',
            'Premio: ₡' . $this->formatAmount($payout->prize_amount),
        ];

        if ((float) $payout->reventado_prize > 0) {
            $lines[] = 'Reventado: ₡' . $this->formatAmount($payout->reventado_prize);
            $lines[] = 'Total: ₡' . $this->formatAmount($payout->total_prize);
        }

        $lines[] = '';
        $lines[] = 'Pasa a cobrar a ' . ($payout->branch?->name ?? 'tu sucursal') . '.';

        return $this->send($request, $user, OutgoingMessage::TYPE_WINNER, implode("\n", $lines));
    }

    public function acknowledgeReply(IntakeRequest $request): ?OutgoingMessage
    {
        return $this->send($request, null, OutgoingMessage::TYPE_REPLY_RECEIVED, implode("\n", [
            'Gracias, recibimos tu respuesta.',
            'Un operador revisará y confirmará tu solicitud.',
        ]));
    }

    private function send(IntakeRequest $request, ?User $user, string $type, string $text): ?OutgoingMessage
    {
        $recipient = $this->recipientFor($request);

        if ($recipient === null) {
            return null;
        }

        $message = OutgoingMessage::create([
            'organization_id' => $request->organization_id,
            'intake_request_id' => $request->id,
            'customer_id' => $request->customer_id,
            'user_id' => $user?->id,
            'channel_type' => $recipient['channel_type'],
            'to_identifier' => $recipient['to_identifier'],
            'message_type' => $type,
            'text' => $text,
            'status' => OutgoingMessage::STATUS_PENDING,
        ]);

        // On the web the Telegram call runs after the operator already got the page back;
        // in console processes (Telegram poller, tests) it runs inline.
        if (app()->runningInConsole()) {
            SendOutgoingMessage::dispatchSync($message->id);
        } else {
            SendOutgoingMessage::dispatchAfterResponse($message->id);
        }

        return $message;
    }

    /**
     * @return array{channel_type:string, to_identifier:string}|null
     */
    private function recipientFor(IntakeRequest $request): ?array
    {
        $incomingMessage = $request->incomingMessage;

        if ($incomingMessage === null || blank($incomingMessage->from_identifier)) {
            return null;
        }

        return [
            'channel_type' => $incomingMessage->channel_type,
            'to_identifier' => (string) $incomingMessage->from_identifier,
        ];
    }

    private function requestLine(IntakeRequest $request): string
    {
        $line = sprintf(
            '• Número %s → ₡%s',
            $request->detected_number ?? '-',
            $this->formatAmount($request->detected_amount),
        );

        if ((float) $request->reventado_amount > 0) {
            $line .= ' + reventado ₡' . $this->formatAmount($request->reventado_amount);
        }

        if ($request->draw !== null) {
            $line .= ' — Sorteo ' . $request->draw->name;
        }

        if ($request->draw_date !== null) {
            $line .= ' (' . $request->draw_date->format('d/m/Y') . ')';
        }

        return $line;
    }

    private function formatAmount(mixed $amount): string
    {
        if ($amount === null) {
            return '-';
        }

        return number_format((float) $amount, (float) $amount === floor((float) $amount) ? 0 : 2, '.', ',');
    }
}
