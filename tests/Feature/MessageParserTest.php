<?php

namespace Tests\Feature;

use App\Services\MessageParserService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MessageParserTest extends TestCase
{
    /**
     * @return array<string, array{string, ?string, array<int, array{int, string}>}>
     */
    public static function messages(): array
    {
        return [
            'number 12 is not the 12md draw' => ['1000 al 12 5pm', '5:00 pm', [[1000, '12']]],
            'amount 12 mil is not the 12md draw' => ['12 mil al 30 7pm', '7:00 pm', [[12000, '30']]],
            'number 12 without draw needs a draw' => ['1000 al 12', null, [[1000, '12']]],
            'bare 12 after the request is the 12md draw' => ['1000 al 28 a las 12', '12:00 md', [[1000, '28']]],
            'explicit 12md' => ['500 al 12 12md', '12:00 md', [[500, '12']]],
            'medio dia' => ['1000 al 12 medio dia', '12:00 md', [[1000, '12']]],
            'spaced pm' => ['500 al 28 2 pm', '2:00 pm', [[500, '28']]],
            'several numbers same amount' => ['1000 al 25 y 28 5pm', '5:00 pm', [[1000, '25'], [1000, '28']]],
            'mixed amounts keep their own amount' => ['1000 al 05 y 7 mil al 9 2pm', '2:00 pm', [[1000, '05'], [7000, '09']]],
            'mixed amounts with plain second amount' => ['1000 al 05 y 500 al 12 7pm', '7:00 pm', [[1000, '05'], [500, '12']]],
        ];
    }

    /**
     * @return array<string, array{string, ?string, array<int, string>, ?int, bool}>
     */
    public static function reventadoMessages(): array
    {
        return [
            'reventado after' => ['1000 al 28 reventado 500 2pm', '2:00 pm', ['28'], 500, false],
            'amount de reventado' => ['1000 al 28 y 500 de reventado 5pm', '5:00 pm', ['28'], 500, false],
            'small amount before reventado' => ['1000 al 28 y 50 reventado 7pm', '7:00 pm', ['28'], 50, false],
            'reventado before the bet' => ['500 reventado 1000 al 12 5pm', '5:00 pm', ['12'], 500, false],
            'reventado 12 mil is not the 12md draw' => ['1000 al 28 reventado 12 mil 2pm', '2:00 pm', ['28'], 12000, false],
            'number listed before reventado amount' => ['1000 al 25 y 28 reventado 500 2pm', '2:00 pm', ['25', '28'], null, true],
            'reventado without amount' => ['1000 al 28 con reventado 2pm', '2:00 pm', ['28'], null, true],
            'no reventado' => ['1000 al 28 2pm', '2:00 pm', ['28'], null, false],
        ];
    }

    #[DataProvider('reventadoMessages')]
    public function test_parser_detects_reventado(string $message, ?string $expectedDraw, array $expectedNumbers, ?int $expectedReventado, bool $needsReview): void
    {
        $result = app(MessageParserService::class)->parse($message);

        $this->assertSame($expectedDraw, $result['draw_reference']);
        $this->assertSame($expectedNumbers, array_column($result['items'], 'detected_number'));
        $this->assertSame($expectedReventado, $result['reventado_amount']);
        $this->assertSame($needsReview, $result['needs_review']);
    }

    #[DataProvider('messages')]
    public function test_parser_detects_draw_and_items(string $message, ?string $expectedDraw, array $expectedItems): void
    {
        $result = app(MessageParserService::class)->parse($message);

        $this->assertSame($expectedDraw, $result['draw_reference']);
        $this->assertSame(
            $expectedItems,
            array_map(static fn (array $item): array => [$item['detected_amount'], $item['detected_number']], $result['items']),
        );
    }
}
