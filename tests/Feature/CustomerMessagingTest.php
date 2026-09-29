<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Draw;
use App\Models\IncomingMessage;
use App\Models\IntakeRequest;
use App\Models\IntakeRequestEvent;
use App\Models\Organization;
use App\Models\OutgoingMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerMessagingTest extends TestCase
{
    use RefreshDatabase;

    private const CHAT_ID = '4001';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::create(2026, 9, 25, 9, 0, 0, 'America/Costa_Rica'));
        Config::set('services.telegram.enabled', true);
        Config::set('services.telegram.bot_token', 'test-token');
    }

    public function test_confirming_a_telegram_request_notifies_the_customer(): void
    {
        [$owner, $branch, $draw] = $this->fixture();
        $request = $this->telegramRequest($branch, $draw, '28', 1000);
        Http::fake($this->telegramResponses());

        $this->actingAs($owner)->post(route('intake-requests.confirm', $request))->assertRedirect();

        Http::assertSent(fn ($http): bool => str_contains($http->url(), '/sendMessage')
            && $http->data()['chat_id'] === self::CHAT_ID
            && str_contains($http->data()['text'], 'confirmada')
            && str_contains($http->data()['text'], 'Número 28 → ₡1,000 — Sorteo 2:00 pm (25/09/2026)'));

        $this->assertDatabaseHas('outgoing_messages', [
            'intake_request_id' => $request->id,
            'message_type' => OutgoingMessage::TYPE_CONFIRMED,
            'status' => OutgoingMessage::STATUS_SENT,
            'external_message_id' => '777',
        ]);
    }

    public function test_rejecting_sends_the_reason_unless_the_operator_opts_out(): void
    {
        [$owner, $branch, $draw] = $this->fixture();
        $notified = $this->telegramRequest($branch, $draw, '28', 1000);
        $silent = $this->telegramRequest($branch, $draw, '30', 500);
        Http::fake($this->telegramResponses());

        $this->actingAs($owner)->post(route('intake-requests.reject', $notified), ['rejection_reason' => 'Número agotado']);
        $this->actingAs($owner)->post(route('intake-requests.reject', $silent), ['rejection_reason' => 'Nota interna', 'notify_customer' => '0']);

        Http::assertSent(fn ($http): bool => str_contains($http->url(), '/sendMessage')
            && str_contains($http->data()['text'], 'Motivo: Número agotado'));
        Http::assertNotSent(fn ($http): bool => str_contains($http->data()['text'] ?? '', 'Nota interna'));
        $this->assertSame(0, $silent->outgoingMessages()->count());
    }

    public function test_simulator_requests_record_the_message_without_sending(): void
    {
        [$owner, $branch, $draw] = $this->fixture();
        $request = $this->telegramRequest($branch, $draw, '28', 1000, Branch::CHANNEL_TYPE_SIMULATED);
        Http::fake();

        $this->actingAs($owner)->post(route('intake-requests.confirm', $request))->assertRedirect();

        Http::assertNothingSent();
        $this->assertDatabaseHas('outgoing_messages', [
            'intake_request_id' => $request->id,
            'status' => OutgoingMessage::STATUS_NOT_SENT,
        ]);
    }

    public function test_telegram_failure_does_not_block_the_confirmation(): void
    {
        [$owner, $branch, $draw] = $this->fixture();
        $request = $this->telegramRequest($branch, $draw, '28', 1000);
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Forbidden: bot was blocked by the user'], 403)]);

        $this->actingAs($owner)->post(route('intake-requests.confirm', $request))->assertRedirect(route('intake-requests.index'));

        $this->assertSame(IntakeRequest::STATUS_CONFIRMED, $request->fresh()->status);
        $this->assertSame(OutgoingMessage::STATUS_FAILED, $request->outgoingMessages()->first()->status);
    }

    public function test_manual_requests_have_no_customer_chat(): void
    {
        [$owner, $branch, $draw] = $this->fixture();
        $request = IntakeRequest::create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'draw_id' => $draw->id,
            'detected_number' => '28',
            'detected_amount' => 1000,
            'raw_text' => 'Manual request from number board',
            'status' => IntakeRequest::STATUS_PENDING,
        ]);
        Http::fake();

        $this->actingAs($owner)->post(route('intake-requests.clarify', $request), ['question' => '¿Número?'])
            ->assertSessionHasErrors('question');
        $this->actingAs($owner)->post(route('intake-requests.confirm', $request))->assertRedirect();

        Http::assertNothingSent();
        $this->assertDatabaseCount('outgoing_messages', 0);
    }

    public function test_operator_can_ask_the_customer_a_question(): void
    {
        [$owner, $branch, $draw] = $this->fixture();
        $request = $this->telegramRequest($branch, $draw, null, 1000, rawText: '1000 al de las 12 2pm');
        Http::fake($this->telegramResponses());

        $this->actingAs($owner)
            ->post(route('intake-requests.clarify', $request), ['question' => '¿Qué número quieres jugar?'])
            ->assertRedirect(route('intake-requests.show', $request));

        Http::assertSent(fn ($http): bool => str_contains($http->url(), '/sendMessage')
            && $http->data()['chat_id'] === self::CHAT_ID
            && str_contains($http->data()['text'], '¿Qué número quieres jugar?')
            && str_contains($http->data()['text'], '1000 al de las 12 2pm'));

        $request->refresh();
        $this->assertTrue($request->isAwaitingCustomerReply());
        $this->assertSame(IntakeRequest::STATUS_NEEDS_REVIEW, $request->status);
        $this->assertTrue($request->events()->where('event_type', IntakeRequestEvent::EVENT_CLARIFICATION_REQUESTED)->exists());

        $this->actingAs($owner)->get(route('intake-requests.show', $request))
            ->assertOk()
            ->assertSee('Awaiting customer reply')
            ->assertSee('¿Qué número quieres jugar?');
    }

    public function test_customer_reply_fills_the_missing_number_without_creating_a_new_request(): void
    {
        [, $branch, $draw] = $this->fixture();
        $request = $this->telegramRequest($branch, $draw, null, 1000);
        $request->update(['awaiting_reply_since' => now()->subMinutes(10)]);
        Http::fake($this->telegramResponses([$this->telegramUpdate(9100, '12')]));

        Artisan::call('telegram:poll');

        $request->refresh();
        $this->assertSame('12', $request->detected_number);
        $this->assertSame($draw->id, $request->draw_id, 'A bare "12" must not change the draw.');
        $this->assertFalse($request->isAwaitingCustomerReply());
        $this->assertSame(IntakeRequest::STATUS_NEEDS_REVIEW, $request->status);
        $this->assertStringContainsString('Customer replied: "12"', $request->notes);
        $this->assertDatabaseCount('requests', 1);
        $this->assertDatabaseHas('incoming_messages', ['external_message_id' => '9100', 'raw_text' => '12']);

        Http::assertSent(fn ($http): bool => str_contains($http->url(), '/sendMessage')
            && str_contains($http->data()['text'], 'recibimos tu respuesta'));
    }

    public function test_customer_reply_can_fill_number_amount_and_draw(): void
    {
        [, $branch] = $this->fixture();
        $request = $this->telegramRequest($branch, null, null, null, rawText: 'quiero jugar');
        $request->update(['awaiting_reply_since' => now()]);
        Http::fake($this->telegramResponses([$this->telegramUpdate(9101, '500 al 45 5pm')]));

        Artisan::call('telegram:poll');

        $request->refresh();
        $this->assertSame('45', $request->detected_number);
        $this->assertEquals(500, $request->detected_amount);
        $this->assertSame('5:00 pm', $request->draw?->name);
        $this->assertDatabaseCount('requests', 1);
    }

    public function test_customer_reply_does_not_overwrite_existing_values(): void
    {
        [, $branch, $draw] = $this->fixture();
        $request = $this->telegramRequest($branch, $draw, null, 1000);
        $request->update(['awaiting_reply_since' => now()]);
        Http::fake($this->telegramResponses([$this->telegramUpdate(9102, '5000 al 45 7pm')]));

        Artisan::call('telegram:poll');

        $request->refresh();
        $this->assertSame('45', $request->detected_number);
        $this->assertEquals(1000, $request->detected_amount);
        $this->assertSame($draw->id, $request->draw_id);
    }

    public function test_message_after_the_reply_window_is_a_new_order(): void
    {
        [, $branch, $draw] = $this->fixture();
        $request = $this->telegramRequest($branch, $draw, null, 1000);
        $request->update(['awaiting_reply_since' => now()->subHours(7)]);
        Http::fake($this->telegramResponses([$this->telegramUpdate(9103, '1000 al 28 2pm')]));

        Artisan::call('telegram:poll');

        $this->assertNull($request->fresh()->detected_number);
        $this->assertDatabaseCount('requests', 2);
    }

    /**
     * @return array{User, Branch, Draw}
     */
    private function fixture(): array
    {
        $organization = Organization::create([
            'name' => 'Messaging Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Telegram Branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@loteriabot',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $owner = User::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_OWNER,
            'name' => 'Owner',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $organization->update(['owner_user_id' => $owner->id]);
        Config::set('services.telegram.default_branch_id', $branch->id);

        $draws = collect([
            ['name' => '12:00 md', 'draw_time' => '12:00:00'],
            ['name' => '2:00 pm', 'draw_time' => '14:00:00'],
            ['name' => '5:00 pm', 'draw_time' => '17:00:00'],
            ['name' => '7:00 pm', 'draw_time' => '19:00:00'],
        ])->map(fn (array $data) => Draw::create([
            'organization_id' => $organization->id,
            'name' => $data['name'],
            'draw_time' => $data['draw_time'],
            'status' => Draw::STATUS_ACTIVE,
        ]));

        return [$owner->fresh(), $branch->fresh(), $draws->firstWhere('name', '2:00 pm')];
    }

    private function telegramRequest(
        Branch $branch,
        ?Draw $draw,
        ?string $number,
        ?float $amount,
        string $channelType = Branch::CHANNEL_TYPE_TELEGRAM,
        string $rawText = '1000 al 28 2pm',
    ): IntakeRequest {
        $incoming = IncomingMessage::create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'channel_type' => $channelType,
            'from_identifier' => self::CHAT_ID,
            'to_identifier' => '@loteriabot',
            'raw_text' => $rawText,
            'external_message_id' => (string) random_int(1, 5000),
            'status' => IncomingMessage::STATUS_RECEIVED,
            'received_at' => now(),
        ]);

        return IntakeRequest::create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'draw_id' => $draw?->id,
            'incoming_message_id' => $incoming->id,
            'detected_number' => $number,
            'detected_amount' => $amount,
            'raw_text' => $rawText,
            'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
        ])->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function telegramUpdate(int $updateId, string $text): array
    {
        return [
            'update_id' => $updateId,
            'message' => [
                'message_id' => $updateId + 100,
                'date' => now()->timestamp,
                'chat' => ['id' => (int) self::CHAT_ID, 'type' => 'private'],
                'from' => ['id' => (int) self::CHAT_ID, 'first_name' => 'Maria'],
                'text' => $text,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function telegramResponses(array $updates = []): array
    {
        return [
            'https://api.telegram.org/bot*/getMe' => Http::response(['ok' => true, 'result' => ['id' => 1, 'is_bot' => true, 'username' => 'loteriabot']]),
            'https://api.telegram.org/bot*/getUpdates' => Http::response(['ok' => true, 'result' => $updates]),
            'https://api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 777]]),
        ];
    }
}
