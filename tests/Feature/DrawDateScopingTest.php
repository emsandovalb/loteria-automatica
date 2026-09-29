<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchDailyClosure;
use App\Models\Draw;
use App\Models\IntakeRequest;
use App\Models\NumberLimit;
use App\Models\Organization;
use App\Models\User;
use App\Services\IntakeMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DrawDateScopingTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-09-25';
    private const YESTERDAY = '2026-09-24';

    protected function setUp(): void
    {
        parent::setUp();

        // Morning in Costa Rica, before the 2pm draw closes.
        $this->travelTo(Carbon::create(2026, 9, 25, 9, 0, 0, 'America/Costa_Rica'));
    }

    public function test_draw_date_uses_business_timezone_late_at_night(): void
    {
        [, $branch, $draw] = $this->fixture();

        // 23:30 in Costa Rica is already the next day in UTC.
        $this->travelTo(Carbon::create(2026, 9, 25, 23, 30, 0, 'America/Costa_Rica'));

        $request = $this->makeRequest($branch, $draw, '28', 100, IntakeRequest::STATUS_PENDING);

        $this->assertSame(self::TODAY, $request->draw_date->toDateString());
        $this->assertSame(self::TODAY, today()->toDateString());
    }

    public function test_limits_do_not_accumulate_across_days(): void
    {
        [$owner, $branch, $draw] = $this->fixture();
        $this->limit($branch, $draw, '28', 1000);
        $this->makeRequest($branch, $draw, '28', 900, IntakeRequest::STATUS_CONFIRMED, self::YESTERDAY);

        $result = $this->intake($owner, $branch, '500 al 28 2pm');

        $this->assertSame(IntakeRequest::STATUS_PENDING, $result['request']->status);
        $this->assertSame(self::TODAY, $result['request']->draw_date->toDateString());
    }

    public function test_limits_still_apply_within_the_same_day(): void
    {
        [$owner, $branch, $draw] = $this->fixture();
        $this->limit($branch, $draw, '28', 1000);
        $this->makeRequest($branch, $draw, '28', 900, IntakeRequest::STATUS_CONFIRMED);

        $result = $this->intake($owner, $branch, '500 al 28 2pm');

        $this->assertSame(IntakeRequest::STATUS_NEEDS_REVIEW, $result['request']->status);
    }

    public function test_confirming_a_request_does_not_count_it_twice_against_the_limit(): void
    {
        [$owner, $branch, $draw] = $this->fixture();
        $this->limit($branch, $draw, '28', 1000);
        $request = $this->makeRequest($branch, $draw, '28', 600, IntakeRequest::STATUS_PENDING);

        $this->actingAs($owner)
            ->post(route('intake-requests.confirm', $request))
            ->assertRedirect(route('intake-requests.index'));

        $this->assertSame(IntakeRequest::STATUS_CONFIRMED, $request->fresh()->status);
    }

    public function test_number_board_only_shows_the_selected_day(): void
    {
        [$owner, $branch, $draw] = $this->fixture();
        $this->makeRequest($branch, $draw, '28', 900, IntakeRequest::STATUS_CONFIRMED, self::YESTERDAY);
        $this->makeRequest($branch, $draw, '28', 100, IntakeRequest::STATUS_CONFIRMED);

        $today = $this->actingAs($owner)->get(route('numbers.index', ['branch_id' => $branch->id, 'draw_id' => $draw->id]));
        $today->assertOk();
        $this->assertEquals(100, $today->viewData('summary')['confirmed_amount']);
        $this->assertTrue($today->viewData('canCreateManualRequests'));

        $yesterday = $this->actingAs($owner)->get(route('numbers.index', [
            'branch_id' => $branch->id,
            'draw_id' => $draw->id,
            'draw_date' => self::YESTERDAY,
        ]));
        $yesterday->assertOk();
        $this->assertEquals(900, $yesterday->viewData('summary')['confirmed_amount']);
        $this->assertFalse($yesterday->viewData('canCreateManualRequests'));
    }

    public function test_closure_totals_use_the_draw_date(): void
    {
        [$owner, $branch, $draw] = $this->fixture();
        $this->makeRequest($branch, $draw, '28', 900, IntakeRequest::STATUS_CONFIRMED, self::YESTERDAY);
        $this->makeRequest($branch, $draw, '30', 100, IntakeRequest::STATUS_CONFIRMED);

        $this->actingAs($owner)->post(route('closures.store'), [
            'branch_id' => $branch->id,
            'closure_date' => self::YESTERDAY,
        ])->assertRedirect(route('closures.index'));

        $closure = BranchDailyClosure::query()->where('branch_id', $branch->id)->firstOrFail();
        $this->assertSame(1, $closure->total_requests);
        $this->assertEquals(900, $closure->total_amount_confirmed);
    }

    public function test_requests_of_a_closed_day_cannot_be_confirmed_rejected_or_edited(): void
    {
        [$owner, $branch, $draw] = $this->fixture();
        $request = $this->makeRequest($branch, $draw, '28', 100, IntakeRequest::STATUS_PENDING);
        $this->closeDay($owner, $branch, self::TODAY);

        $this->actingAs($owner)->post(route('intake-requests.confirm', $request))->assertForbidden();
        $this->actingAs($owner)->post(route('intake-requests.reject', $request), ['rejection_reason' => 'Late'])->assertForbidden();
        $this->actingAs($owner)->patch(route('intake-requests.update', $request), ['detected_amount' => 200])->assertForbidden();

        $this->assertSame(IntakeRequest::STATUS_PENDING, $request->fresh()->status);
        $this->assertEquals(100, $request->fresh()->detected_amount);
    }

    public function test_requests_of_an_open_day_are_not_affected_by_other_closures(): void
    {
        [$owner, $branch, $draw] = $this->fixture();
        $request = $this->makeRequest($branch, $draw, '28', 100, IntakeRequest::STATUS_PENDING);
        $this->closeDay($owner, $branch, self::YESTERDAY);

        $this->actingAs($owner)->post(route('intake-requests.confirm', $request))->assertRedirect();

        $this->assertSame(IntakeRequest::STATUS_CONFIRMED, $request->fresh()->status);
    }

    public function test_manual_board_request_is_blocked_when_the_day_is_closed(): void
    {
        [$owner, $branch, $draw] = $this->fixture();
        $this->closeDay($owner, $branch, self::TODAY);

        $this->actingAs($owner)->post(route('numbers.store'), [
            'branch_id' => $branch->id,
            'draw_id' => $draw->id,
            'number' => '28',
            'amount' => 100,
        ])->assertSessionHasErrors('number');

        $this->assertDatabaseCount('requests', 0);
    }

    public function test_order_arriving_after_the_day_was_closed_is_rejected_and_the_customer_told(): void
    {
        [$owner, $branch, $draw] = $this->fixture();
        $this->closeDay($owner, $branch, self::TODAY);

        $result = $this->intake($owner, $branch, '1000 al 28 2pm');

        $this->assertSame(IntakeRequest::STATUS_REJECTED, $result['request']->status);
        $this->assertNotNull($result['request']->rejected_at);
        $this->assertSame('Branch day is already closed. Rejected automatically.', $result['request']->notes);
        $this->assertSame('day_closed', $result['message_response']->response_type);
        $this->assertStringContainsString('Las ventas de hoy ya cerraron', $result['customer_confirmation_text']);
        $this->assertStringNotContainsString('Pendiente de revisión', $result['customer_confirmation_text']);
    }

    public function test_order_is_accepted_when_only_another_day_is_closed(): void
    {
        [$owner, $branch] = $this->fixture();
        $this->closeDay($owner, $branch, self::YESTERDAY);

        $result = $this->intake($owner, $branch, '1000 al 28 2pm');

        $this->assertSame(IntakeRequest::STATUS_PENDING, $result['request']->status);
    }

    /**
     * @return array{User, Branch, Draw}
     */
    private function fixture(): array
    {
        $organization = Organization::create([
            'name' => 'Draw Date Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Main Branch',
            'channel_type' => Branch::CHANNEL_TYPE_SIMULATED,
            'channel_identifier' => '+50255514001',
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

        $draw = Draw::create([
            'organization_id' => $organization->id,
            'name' => '2:00 pm',
            'draw_time' => '14:00:00',
            'close_time' => '13:55:00',
            'cutoff_minutes_before' => 5,
            'timezone' => 'America/Costa_Rica',
            'closes_at_next_day' => false,
            'is_accepting_requests' => true,
            'status' => Draw::STATUS_ACTIVE,
        ]);

        return [$owner->fresh(), $branch->fresh(), $draw->fresh()];
    }

    private function makeRequest(Branch $branch, Draw $draw, string $number, float $amount, string $status, ?string $drawDate = null): IntakeRequest
    {
        return IntakeRequest::create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'draw_id' => $draw->id,
            'draw_date' => $drawDate,
            'detected_number' => $number,
            'detected_amount' => $amount,
            'raw_text' => sprintf('%s al %s', $amount, $number),
            'status' => $status,
        ])->fresh();
    }

    private function limit(Branch $branch, Draw $draw, string $number, float $maxAmount): void
    {
        NumberLimit::create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'draw_id' => $draw->id,
            'number' => $number,
            'max_amount' => $maxAmount,
        ]);
    }

    private function intake(User $owner, Branch $branch, string $message): array
    {
        return app(IntakeMessageService::class)->create(
            user: $owner,
            branch: $branch,
            customerPhone: '+50255514999',
            customerName: 'Cliente',
            rawText: $message,
        );
    }

    private function closeDay(User $owner, Branch $branch, string $date): void
    {
        BranchDailyClosure::create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'closed_by' => $owner->id,
            'closure_date' => $date,
            'total_requests' => 0,
            'total_confirmed' => 0,
            'total_rejected' => 0,
            'total_pending' => 0,
            'total_amount_confirmed' => 0,
            'closed_at' => now(),
        ]);
    }
}
