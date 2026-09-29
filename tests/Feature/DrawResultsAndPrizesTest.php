<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchDailyClosure;
use App\Models\Draw;
use App\Models\DrawResult;
use App\Models\IncomingMessage;
use App\Models\IntakeRequest;
use App\Models\PrizePayout;
use App\Models\User;
use App\Services\IntakeMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DrawResultsAndPrizesTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-09-25';

    private User $owner;
    private Branch $central;
    private Branch $north;
    private Draw $draw;

    protected function setUp(): void
    {
        parent::setUp();

        // Morning: requests are taken; each test moves past the 2pm draw before entering results.
        $this->travelTo(Carbon::create(2026, 9, 25, 9, 0, 0, 'America/Costa_Rica'));
        $this->seed();

        $this->owner = $this->user('owner@local.test');
        $this->central = Branch::query()->where('name', 'Central Branch')->firstOrFail();
        $this->north = Branch::query()->where('name', 'North Branch')->firstOrFail();
        $this->draw = Draw::query()->where('name', '2:00 pm')->firstOrFail();
        $this->draw->update(['prize_multiplier' => 80, 'reventado_multiplier' => 5]);

        // Seeded sample requests would only add noise to the totals.
        IntakeRequest::query()->delete();
    }

    public function test_result_creates_payouts_only_for_confirmed_bets_on_that_number_draw_and_day(): void
    {
        $winner = $this->bet($this->central, '28', 1000);
        $this->bet($this->north, '28', 500);
        $this->bet($this->central, '28', 700, IntakeRequest::STATUS_PENDING);
        $this->bet($this->central, '28', 900, IntakeRequest::STATUS_REJECTED);
        $this->bet($this->central, '30', 1000);
        $this->bet($this->central, '28', 1000, drawDate: '2026-09-24');

        $this->enterResult('28')->assertRedirect(route('results.index', ['draw_date' => self::TODAY]));

        $payouts = PrizePayout::query()->orderBy('bet_amount', 'desc')->get();
        $this->assertCount(2, $payouts);
        $this->assertSame($winner->id, $payouts[0]->intake_request_id);
        $this->assertEquals(80000, $payouts[0]->total_prize);
        $this->assertEquals(40000, $payouts[1]->total_prize);
        $this->assertSame($this->north->id, $payouts[1]->branch_id);
    }

    public function test_reventado_pays_extra_only_when_the_ball_comes_out(): void
    {
        $this->bet($this->central, '28', 1000, reventado: 500);

        $this->enterResult('28', reventadoHit: true);
        $payout = PrizePayout::query()->firstOrFail();
        $this->assertEquals(80000, $payout->prize_amount);
        $this->assertEquals(2500, $payout->reventado_prize);
        $this->assertEquals(82500, $payout->total_prize);

        $this->enterResult('28', reventadoHit: false);
        $this->assertEquals(80000, PrizePayout::query()->firstOrFail()->total_prize);
    }

    public function test_prizes_use_the_multiplier_at_the_time_of_the_result(): void
    {
        $this->bet($this->central, '28', 1000);
        $this->enterResult('28');

        $this->draw->update(['prize_multiplier' => 90]);

        $this->assertEquals(80000, PrizePayout::query()->firstOrFail()->total_prize);
        $this->assertEquals(80, DrawResult::query()->firstOrFail()->prize_multiplier);
    }

    public function test_result_cannot_be_entered_before_the_draw_takes_place(): void
    {
        $this->actingAs($this->owner)->post(route('results.store'), [
            'draw_id' => $this->draw->id,
            'draw_date' => self::TODAY,
            'winning_number' => '28',
        ])->assertSessionHasErrors('winning_number');

        $this->assertDatabaseCount('draw_results', 0);
    }

    public function test_only_owners_and_admins_enter_results(): void
    {
        $this->afterDraw();

        foreach (['seller-1@local.test', 'viewer@local.test'] as $email) {
            $this->actingAs($this->user($email))->post(route('results.store'), [
                'draw_id' => $this->draw->id,
                'draw_date' => self::TODAY,
                'winning_number' => '28',
            ])->assertForbidden();
        }

        $this->actingAs($this->user('admin@local.test'))->post(route('results.store'), [
            'draw_id' => $this->draw->id,
            'draw_date' => self::TODAY,
            'winning_number' => '28',
        ])->assertRedirect();
        $this->assertDatabaseCount('draw_results', 1);
    }

    public function test_a_correction_recalculates_winners_until_they_are_notified(): void
    {
        $this->bet($this->central, '28', 1000);
        $this->bet($this->central, '82', 1000);

        $this->enterResult('28');
        $this->enterResult('82');

        $this->assertSame(['82'], PrizePayout::query()->pluck('number')->all());
        $this->assertDatabaseCount('draw_results', 1);

        Http::fake();
        $this->actingAs($this->owner)->post(route('results.notify', DrawResult::query()->firstOrFail()))->assertRedirect();

        $this->enterResult('28')->assertSessionHasErrors('winning_number');
        $this->assertSame(['82'], PrizePayout::query()->pluck('number')->all());
    }

    public function test_notifying_winners_messages_them_through_their_chat(): void
    {
        Config::set('services.telegram.bot_token', 'test-token');
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
        $this->bet($this->central, '28', 1000, telegramChat: '4001');
        $this->enterResult('28');

        $this->actingAs($this->owner)->post(route('results.notify', DrawResult::query()->firstOrFail()))->assertRedirect();

        Http::assertSent(fn ($http): bool => str_contains($http->url(), '/sendMessage')
            && $http->data()['chat_id'] === '4001'
            && str_contains($http->data()['text'], 'Felicidades')
            && str_contains($http->data()['text'], 'Premio: ₡80,000')
            && str_contains($http->data()['text'], 'Central Branch'));
        $this->assertNotNull(DrawResult::query()->firstOrFail()->winners_notified_at);
    }

    public function test_after_the_result_pending_bets_can_be_rejected_but_not_confirmed(): void
    {
        $pending = $this->bet($this->central, '28', 1000, IntakeRequest::STATUS_PENDING);
        $this->enterResult('28');

        $this->actingAs($this->owner)->post(route('intake-requests.confirm', $pending))->assertForbidden();
        $this->actingAs($this->owner)->patch(route('intake-requests.update', $pending), ['detected_amount' => 5000])->assertForbidden();
        $this->actingAs($this->owner)->post(route('intake-requests.reject', $pending), ['rejection_reason' => 'Llegó tarde', 'notify_customer' => '0'])->assertRedirect();

        $this->assertSame(IntakeRequest::STATUS_REJECTED, $pending->fresh()->status);
        $this->assertDatabaseCount('prize_payouts', 0);
    }

    public function test_the_selling_branch_pays_its_prizes(): void
    {
        $this->bet($this->central, '28', 1000);
        $this->enterResult('28');
        $payout = PrizePayout::query()->firstOrFail();

        $this->actingAs($this->user('seller-2@local.test'))->post(route('payouts.pay', $payout))->assertForbidden();
        $this->actingAs($this->user('viewer@local.test'))->post(route('payouts.pay', $payout))->assertForbidden();

        $seller = $this->user('seller-1@local.test');
        $this->actingAs($seller)->post(route('payouts.pay', $payout))->assertRedirect();

        $payout->refresh();
        $this->assertTrue($payout->isPaid());
        $this->assertSame($seller->id, $payout->paid_by);
        $this->assertTrue(DrawResult::query()->firstOrFail()->isLocked());

        $this->actingAs($seller)->get(route('payouts.index', ['draw_date' => self::TODAY]))->assertOk()->assertSee('28');
        $this->actingAs($this->user('seller-2@local.test'))->get(route('payouts.index', ['draw_date' => self::TODAY]))
            ->assertOk()->assertSee('No winners for this date.');
    }

    public function test_settlement_is_sales_including_reventado_minus_prizes(): void
    {
        $this->bet($this->central, '28', 1000, reventado: 200);
        $this->bet($this->central, '30', 3000);
        $this->bet($this->north, '45', 2000);
        $this->bet($this->central, '28', 5000, IntakeRequest::STATUS_PENDING);
        $this->enterResult('28');

        $response = $this->actingAs($this->owner)->get(route('settlement.index', ['draw_date' => self::TODAY]))->assertOk();

        $this->assertEquals(['sales' => 6200.0, 'prizes' => 80000.0, 'net' => -73800.0], $response->viewData('totals'));
        $central = collect($response->viewData('rows'))->firstWhere('branch.id', $this->central->id);
        $this->assertEquals(['sales' => 4200.0, 'prizes' => 80000.0, 'net' => -75800.0], $central['totals']);

        $this->actingAs($this->user('seller-2@local.test'))->get(route('settlement.index', ['draw_date' => self::TODAY]))
            ->assertOk()
            ->assertDontSee('Central Branch');
    }

    public function test_daily_closure_snapshots_the_prizes(): void
    {
        $this->bet($this->central, '28', 1000, reventado: 200);
        $this->enterResult('28', reventadoHit: true);

        $this->actingAs($this->owner)->post(route('closures.store'), [
            'branch_id' => $this->central->id,
            'closure_date' => self::TODAY,
        ])->assertRedirect(route('closures.index'));

        $closure = BranchDailyClosure::query()->firstOrFail();
        $this->assertEquals(1200, $closure->total_amount_confirmed);
        $this->assertEquals(81000, $closure->total_prizes_amount);
    }

    public function test_reventado_on_a_draw_without_reventado_goes_to_review(): void
    {
        $this->draw->update(['reventado_multiplier' => null]);

        $result = app(IntakeMessageService::class)->create(
            user: $this->owner,
            branch: $this->central,
            customerPhone: '+50255519999',
            customerName: 'Cliente',
            rawText: '1000 al 28 reventado 500 2pm',
        );

        $this->assertSame(IntakeRequest::STATUS_NEEDS_REVIEW, $result['request']->status);
        $this->assertEquals(500, $result['request']->reventado_amount);
        $this->assertStringContainsString('does not offer reventado', $result['request']->notes);
        $this->assertStringContainsString('+ reventado ₡500', $result['customer_confirmation_text']);
    }

    public function test_result_pages_render(): void
    {
        $this->bet($this->central, '28', 1000);
        $this->enterResult('28');

        foreach (['results.index', 'payouts.index', 'settlement.index'] as $route) {
            $this->actingAs($this->owner)->get(route($route, ['draw_date' => self::TODAY]))->assertOk();
        }

        $this->actingAs($this->owner)->get(route('results.index', ['draw_date' => self::TODAY]))
            ->assertSee('Notify winners')
            ->assertSee('Correct result');
    }

    private function afterDraw(): void
    {
        $this->travelTo(Carbon::create(2026, 9, 25, 14, 30, 0, 'America/Costa_Rica'));
    }

    private function enterResult(string $number, bool $reventadoHit = false)
    {
        $this->afterDraw();

        return $this->actingAs($this->owner)->post(route('results.store'), [
            'draw_id' => $this->draw->id,
            'draw_date' => self::TODAY,
            'winning_number' => $number,
            'reventado_hit' => $reventadoHit ? '1' : '0',
        ]);
    }

    private function bet(
        Branch $branch,
        string $number,
        float $amount,
        string $status = IntakeRequest::STATUS_CONFIRMED,
        ?float $reventado = null,
        string $drawDate = self::TODAY,
        ?string $telegramChat = null,
    ): IntakeRequest {
        $incoming = $telegramChat === null ? null : IncomingMessage::create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'from_identifier' => $telegramChat,
            'to_identifier' => '@loteriabot',
            'raw_text' => "{$amount} al {$number} 2pm",
            'status' => IncomingMessage::STATUS_RECEIVED,
            'received_at' => now(),
        ]);

        return IntakeRequest::create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'draw_id' => $this->draw->id,
            'draw_date' => $drawDate,
            'incoming_message_id' => $incoming?->id,
            'detected_number' => $number,
            'detected_amount' => $amount,
            'reventado_amount' => $reventado,
            'raw_text' => "{$amount} al {$number} 2pm",
            'status' => $status,
        ]);
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
