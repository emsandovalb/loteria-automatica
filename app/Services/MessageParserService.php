<?php

namespace App\Services;

use Illuminate\Support\Str;

class MessageParserService
{
    public function parse(string $rawText): array
    {
        $normalizedText = $this->normalize($rawText);
        $segments = $this->extractRequestSegments($normalizedText);
        $remainder = $this->withoutSegments($normalizedText, $segments);
        $reventado = $this->detectReventado($remainder);
        // The reventado phrase ("reventado 12 mil") must not be read as a draw ("12").
        $drawReference = $this->detectDrawReference($reventado['remainder']);

        return $this->applyReventado($this->buildResult($segments, $drawReference), $reventado);
    }

    /**
     * @param  array<int, string>  $segments
     */
    private function buildResult(array $segments, ?string $drawReference): array
    {
        $items = [];

        foreach ($segments as $segment) {
            $amount = $this->detectAmount($segment);
            $numbers = $this->detectNumbers($segment);

            if ($amount === null || $numbers === []) {
                continue;
            }

            foreach ($numbers as $number) {
                $items[] = [
                    'detected_amount' => $amount,
                    'detected_number' => $number,
                ];
            }
        }

        if ($items === []) {
            return [
                'items' => [],
                'detected_amount' => null,
                'detected_number' => null,
                'draw_reference' => $drawReference,
                'confidence' => 0.1,
                'needs_review' => true,
                'reason' => $drawReference === null
                    ? 'Could not detect a valid amount/number pattern.'
                    : 'Draw schedule is required. Manual review required.',
                'parser_type' => 'invalid',
            ];
        }

        if (count($segments) > 1) {
            return [
                'items' => $items,
                'detected_amount' => null,
                'detected_number' => null,
                'draw_reference' => $drawReference,
                'confidence' => 0.88,
                'needs_review' => true,
                'reason' => 'Multiple request patterns detected. Manual review required.',
                'parser_type' => 'multiple_request_patterns',
            ];
        }

        if (count($items) > 1) {
            return [
                'items' => $items,
                'detected_amount' => null,
                'detected_number' => null,
                'draw_reference' => $drawReference,
                'confidence' => 0.9,
                'needs_review' => true,
                'reason' => 'Multiple numbers detected for the same amount. Manual review required.',
                'parser_type' => 'multiple_numbers_same_amount',
            ];
        }

        return [
            'items' => $items,
            'detected_amount' => $items[0]['detected_amount'],
            'detected_number' => $items[0]['detected_number'],
            'draw_reference' => $drawReference,
            'confidence' => 0.97,
            'needs_review' => $drawReference === null,
            'reason' => $drawReference === null
                ? 'Draw schedule is required. Manual review required.'
                : null,
            'parser_type' => 'single_request',
        ];
    }

    /**
     * Finds an extra "reventado" bet such as "500 reventado", "500 de reventado" or "reventado 500".
     *
     * @return array{requested: bool, amount: ?int, remainder: string}
     */
    private function detectReventado(string $remainder): array
    {
        $amount = '(\d+\s*mil|\d+|mil)';
        $keyword = '(?:reventados?|rev)';
        $patterns = [
            '/(?:^|\s)' . $amount . '\s*(?:de\s+|al\s+)?' . $keyword . '\b/u',
            '/\b' . $keyword . '\s*(?:de\s+|con\s+|por\s+)?' . $amount . '(?=\s|$)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $remainder, $matches) === 1) {
                return [
                    'requested' => true,
                    'amount' => $this->detectAmount(trim($matches[1])),
                    'remainder' => str_replace($matches[0], ' ', $remainder),
                ];
            }
        }

        if (preg_match('/\b(?:con\s+)?' . $keyword . '\b/u', $remainder, $matches) === 1) {
            return ['requested' => true, 'amount' => null, 'remainder' => str_replace($matches[0], ' ', $remainder)];
        }

        return ['requested' => false, 'amount' => null, 'remainder' => $remainder];
    }

    /**
     * @param  array{requested: bool, amount: ?int, remainder: string}  $reventado
     */
    private function applyReventado(array $result, array $reventado): array
    {
        $result['reventado_amount'] = null;

        if (! $reventado['requested'] || $result['items'] === []) {
            return $result;
        }

        if ($reventado['amount'] !== null && count($result['items']) === 1) {
            $result['items'][0]['reventado_amount'] = $reventado['amount'];
            $result['reventado_amount'] = $reventado['amount'];

            return $result;
        }

        // Without an amount, or with several numbers, we cannot tell which bet carries the reventado.
        $result['needs_review'] = true;
        $result['reason'] = trim(implode(' ', array_filter([
            $result['reason'],
            $reventado['amount'] === null
                ? 'Reventado requested without an amount. Manual review required.'
                : 'Reventado could not be assigned to a single number. Manual review required.',
        ])));

        return $result;
    }

    private function normalize(string $rawText): string
    {
        $normalizedText = Str::ascii(mb_strtolower(trim($rawText)));
        $normalizedText = preg_replace('/(?<=\d)[,\.](?=\d)/u', '', $normalizedText) ?? $normalizedText;
        $normalizedText = preg_replace('/[^\p{L}\p{N}\s#]/u', ' ', $normalizedText) ?? $normalizedText;

        return preg_replace('/\s+/u', ' ', $normalizedText) ?? $normalizedText;
    }

    /**
     * Looks for the draw only in text outside the request segments, so numbers and
     * amounts such as "al 12" or "12 mil" are never mistaken for the 12:00 md draw.
     */
    private function detectDrawReference(string $text): ?string
    {
        $patterns = [
            '12:00 md' => [
                '/\bmedio\s*dia\b/u',
                '/\bmediodia\b/u',
                '/\b12\s*(?:md|m\s*d)\b/u',
                '/\b12\s*pm\b/u',
            ],
            '2:00 pm' => [
                '/\b2\s*pm\b/u',
            ],
            '5:00 pm' => [
                '/\b5\s*pm\b/u',
            ],
            '7:00 pm' => [
                '/\b7\s*pm\b/u',
            ],
        ];

        foreach ($patterns as $reference => $regexList) {
            foreach ($regexList as $regex) {
                if (preg_match($regex, $text) === 1) {
                    return $reference;
                }
            }
        }

        // A bare "12" (e.g. "1000 al 28 a las 12") is only accepted when nothing more specific matched.
        if (preg_match('/\b12\b/u', $text) === 1) {
            return '12:00 md';
        }

        return null;
    }

    /**
     * @param  array<int, string>  $segments
     */
    private function withoutSegments(string $normalizedText, array $segments): string
    {
        foreach ($segments as $segment) {
            $position = strpos($normalizedText, $segment);

            if ($position !== false) {
                $normalizedText = substr_replace($normalizedText, ' ', $position, strlen($segment));
            }
        }

        return $normalizedText;
    }

    /**
     * @return array<int, string>
     */
    private function extractRequestSegments(string $normalizedText): array
    {
        preg_match_all(
            // A listed number must not start a new amount ("... y 7 mil al 9"), otherwise the
            // next request would be swallowed into this one with the wrong amount. Likewise
            // "y 50 reventado" is a reventado amount, but "y 28 reventado 500" lists number 28.
            '/(?:^|\s)((?:\d+\s*mil|\d+|mil)\s*(?:al|numero|num|#)\s*\d{1,2}(?:\s*(?:y|,)\s*\d{1,2}(?!\d)(?!\s*(?:(?:mil|al|numero|num)\b|#|(?:de\s+)?(?:reventados?|rev)\b(?!\s*(?:de\s+|con\s+|por\s+)?(?:\d+(?!\d)(?!\s*(?:pm|md)\b)|mil\b)))))*)(?=\s|$)/u',
            $normalizedText,
            $matches
        );

        return array_values(array_filter(array_map('trim', $matches[1] ?? [])));
    }

    private function detectAmount(string $normalizedText): ?int
    {
        if (preg_match('/(?:^|\s)(\d+)\s*mil\b/u', $normalizedText, $matches)) {
            return (int) $matches[1] * 1000;
        }

        if (preg_match('/\bmil\b/u', $normalizedText)) {
            return 1000;
        }

        if (preg_match('/\b(\d{1,7})\b/u', $normalizedText, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function detectNumbers(string $normalizedText): array
    {
        if (! preg_match('/(?:al|numero|num|#)\s*(.+)$/u', $normalizedText, $matches)) {
            return [];
        }

        $numberText = trim($matches[1]);
        $segments = preg_split('/\s*(?:y|,)\s*/u', $numberText) ?: [];
        $numbers = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);

            if (preg_match('/^\d{1,2}$/u', $segment) !== 1) {
                continue;
            }

            $numbers[] = str_pad((string) ((int) $segment), 2, '0', STR_PAD_LEFT);
        }

        return array_values(array_unique($numbers));
    }
}
