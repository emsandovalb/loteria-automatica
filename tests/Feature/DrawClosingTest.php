<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Draw;
use App\Models\IntakeRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\IntakeMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DrawClosingTest extends TestCase
{
    use RefreshDatabase;

    public function test_draw_is_open_before_close_time(): void
    {
        $draw = $this->createDraw(closeTime: '13:55:00');

        $this->assertTrue($draw->isOpenForIntake(Carbon::create(2026, 5, 26, 13, 54, 0, 'America/Costa_Rica')));
        $this->assertSame(null, $draw->closingReason(Carbon::create(2026, 5, 26, 13, 54, 0, 'America/Costa_Rica')));
    }

    public function test_draw_is_closed_after_close_time(): void
    {
        $draw = $this->createDraw(closeTime: '13:55:00');

        $this->assertFalse($draw->isOpenForIntake(Carbon::create(2026, 5, 26, 13, 56, 0, 'America/Costa_Rica')));
        $this->assertSame('closed_by_time', $draw->closingReason(Carbon::create(2026, 5, 26, 13, 56, 0, 'America/Costa_Rica')));
    }

    public function test_manually_closed_draw_creates_needs_review_from_simulator(): void
    {
        [$owner, $branch, $draw] = $this->createOrganizationFixture();
        $draw->update(['is_accepting_requests' => false]);

        $this->actingAs($owner)->post(route('simulator.store'), [
            'branch_id' => $branch->id,
            'customer_phone' => '+50255512001',
            'customer_name' => 'Manual Close',
            'raw_message' => '1000 al 28 2pm',
        ])->assertRedirect(route('simulator.index'));

        $this->assertDatabaseHas('requests', [
            'organization_id' => $owner->organization_id,
            'branch_id' => $branch->id,
            'draw_id' => $draw->id,
            'detected_number' => '28',
            'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
            'notes' => 'Draw is closed for intake. Manual review required.',
        ]);
    }

    public function test_telegram_intake_for_closed_draw_creates_needs_review(): void
    {
        [$owner, $branch, $draw] = $this->createOrganizationFixture();
        $draw->update(['is_accepting_requests' => false]);

        $service = app(IntakeMessageService::class);
        $result = $service->create(
            user: $owner,
            branch: $branch,
            customerPhone: '+50255512002',
            customerName: 'Telegram Close',
            rawText: '1000 al 28 2pm',
            externalMessageId: 'tg-1001',
            channelType: Branch::CHANNEL_TYPE_TELEGRAM,
            fromIdentifier: '4002',
            toIdentifier: '@loteriabot',
        );

        $this->assertSame(IntakeRequest::STATUS_NEEDS_REVIEW, $result['request']->status);
        $this->assertSame('Draw is closed for intake. Manual review required.', $result['request']->notes);
        $this->assertSame('manual_review', $result['message_response']->response_type);
        $this->assertStringContainsString('Hemos recibido tu solicitud', $result['customer_confirmation_text']);
        $this->assertStringContainsString('horario de recepción', $result['customer_confirmation_text']);
    }

    public function test_simulator_intake_for_closed_draw_creates_needs_review(): void
    {
        [$owner, $branch, $draw] = $this->createOrganizationFixture();
        $draw->update(['is_accepting_requests' => false]);

        $this->actingAs($owner)->post(route('simulator.store'), [
            'branch_id' => $branch->id,
            'customer_phone' => '+50255512003',
            'customer_name' => 'Simulator Close',
            'raw_message' => '1000 al 28 2pm',
        ])->assertRedirect(route('simulator.index'));

        $this->assertDatabaseHas('requests', [
            'organization_id' => $owner->organization_id,
            'branch_id' => $branch->id,
            'draw_id' => $draw->id,
            'detected_number' => '28',
            'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
            'notes' => 'Draw is closed for intake. Manual review required.',
        ]);
    }

    public function test_manual_number_board_request_for_closed_draw_creates_needs_review(): void
    {
        [$owner, $branch, $draw] = $this->createOrganizationFixture();
        $draw->update(['is_accepting_requests' => false]);

        $this->actingAs($owner)->post(route('numbers.store'), [
            'branch_id' => $branch->id,
            'draw_id' => $draw->id,
            'number' => '28',
            'amount' => 100,
            'customer_phone' => '+50255512004',
            'notes' => 'Manual board note',
        ])->assertRedirect(route('numbers.index', ['branch_id' => $branch->id, 'draw_id' => $draw->id]));

        $this->assertDatabaseHas('requests', [
            'organization_id' => $owner->organization_id,
            'branch_id' => $branch->id,
            'draw_id' => $draw->id,
            'detected_number' => '28',
            'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
            'notes' => "Draw is closed for intake. Manual review required.\n\nManual board note",
        ]);
    }

    public function test_owner_can_edit_draw_close_settings(): void
    {
        [$owner, , $draw] = $this->createOrganizationFixture();

        $this->actingAs($owner)->patch(route('draws.update', $draw), [
            'name' => '2:00 pm',
            'draw_time' => '14:00:00',
            'close_time' => '13:50:00',
            'cutoff_minutes_before' => 10,
            'is_accepting_requests' => false,
            'status' => Draw::STATUS_ACTIVE,
        ])->assertRedirect(route('draws.index'));

        $this->assertDatabaseHas('draws', [
            'id' => $draw->id,
            'close_time' => '13:50:00',
            'cutoff_minutes_before' => 10,
            'is_accepting_requests' => false,
        ]);
    }

    public function test_admin_can_edit_draw_close_settings(): void
    {
        [$owner, , $draw, $admin] = $this->createOrganizationFixture(withAdmin: true);

        $this->actingAs($admin)->patch(route('draws.update', $draw), [
            'name' => '2:00 pm',
            'draw_time' => '14:00:00',
            'close_time' => '13:45:00',
            'cutoff_minutes_before' => 15,
            'is_accepting_requests' => true,
            'status' => Draw::STATUS_ACTIVE,
        ])->assertRedirect(route('draws.index'));

        $this->assertDatabaseHas('draws', [
            'id' => $draw->id,
            'close_time' => '13:45:00',
            'cutoff_minutes_before' => 15,
            'is_accepting_requests' => true,
        ]);
    }

    public function test_seller_and_viewer_cannot_edit_draw_close_settings(): void
    {
        [$owner, $branch, $draw, $admin, $seller, $viewer] = $this->createOrganizationFixture(withAdmin: true, withSellerAndViewer: true);

        $this->actingAs($seller)->patch(route('draws.update', $draw), [
            'name' => '2:00 pm',
            'draw_time' => '14:00:00',
            'close_time' => '13:40:00',
            'cutoff_minutes_before' => 20,
            'is_accepting_requests' => false,
            'status' => Draw::STATUS_ACTIVE,
        ])->assertForbidden();

        $this->actingAs($viewer)->patch(route('draws.update', $draw), [
            'name' => '2:00 pm',
            'draw_time' => '14:00:00',
            'close_time' => '13:40:00',
            'cutoff_minutes_before' => 20,
            'is_accepting_requests' => false,
            'status' => Draw::STATUS_ACTIVE,
        ])->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Branch, 2: Draw, 3?: User, 4?: User, 5?: User}
     */
    private function createOrganizationFixture(bool $withAdmin = false, bool $withSellerAndViewer = false): array
    {
        $organization = Organization::create([
            'name' => 'Draw Close Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Main Branch',
            'channel_type' => Branch::CHANNEL_TYPE_SIMULATED,
            'channel_identifier' => '+50255513001',
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

        if (! $withAdmin && ! $withSellerAndViewer) {
            return [$owner->fresh(), $branch->fresh(), $draw->fresh()];
        }

        $admin = User::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_ADMIN,
            'name' => 'Admin',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        if (! $withSellerAndViewer) {
            return [$owner->fresh(), $branch->fresh(), $draw->fresh(), $admin->fresh()];
        }

        $seller = User::create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'role' => User::ROLE_SELLER,
            'name' => 'Seller',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $viewer = User::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_VIEWER,
            'name' => 'Viewer',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        return [$owner->fresh(), $branch->fresh(), $draw->fresh(), $admin->fresh(), $seller->fresh(), $viewer->fresh()];
    }

    private function createDraw(?string $closeTime = null): Draw
    {
        $organization = Organization::create([
            'name' => 'Timing Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        return Draw::create([
            'organization_id' => $organization->id,
            'name' => '2:00 pm',
            'draw_time' => '14:00:00',
            'close_time' => $closeTime,
            'cutoff_minutes_before' => 5,
            'timezone' => 'America/Costa_Rica',
            'closes_at_next_day' => false,
            'is_accepting_requests' => true,
            'status' => Draw::STATUS_ACTIVE,
        ]);
    }
}
