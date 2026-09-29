<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Draw;
use App\Models\IntakeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConfirmationRequirementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /**
     * @return array<string, array{?string, ?float, string}>
     */
    public static function incompleteRequests(): array
    {
        return [
            'missing number and amount' => [null, null, 'number, amount'],
            'missing number' => [null, 1000, 'number'],
            'missing amount' => ['28', null, 'amount'],
        ];
    }

    #[DataProvider('incompleteRequests')]
    public function test_incomplete_request_cannot_be_confirmed(?string $number, ?float $amount, string $missing): void
    {
        $owner = User::query()->where('email', 'owner@local.test')->firstOrFail();
        $request = $this->makeRequest($number, $amount);

        $this->actingAs($owner)
            ->post(route('intake-requests.confirm', $request))
            ->assertRedirect(route('intake-requests.edit', $request))
            ->assertSessionHas('status', sprintf('Cannot confirm yet: %s required.', $missing));

        $request->refresh();
        $this->assertSame(IntakeRequest::STATUS_NEEDS_REVIEW, $request->status);
        $this->assertNull($request->confirmed_at);
        $this->assertStringContainsString('Missing ' . $missing, $request->notes);
    }

    public function test_request_list_offers_complete_instead_of_confirm_when_data_is_missing(): void
    {
        $owner = User::query()->where('email', 'owner@local.test')->firstOrFail();
        IntakeRequest::query()->delete();
        $this->makeRequest(null, null);

        $this->actingAs($owner)
            ->get(route('intake-requests.index'))
            ->assertOk()
            ->assertSee('Complete')
            ->assertDontSee('>Confirm</button>', false);
    }

    public function test_request_completed_by_the_operator_can_be_confirmed(): void
    {
        $owner = User::query()->where('email', 'owner@local.test')->firstOrFail();
        $request = $this->makeRequest(null, null);

        $this->actingAs($owner)->patch(route('intake-requests.update', $request), [
            'detected_number' => '28',
            'detected_amount' => 1000,
            'draw_id' => $request->draw_id,
        ]);

        $this->actingAs($owner)->post(route('intake-requests.confirm', $request))->assertRedirect(route('intake-requests.index'));

        $this->assertSame(IntakeRequest::STATUS_CONFIRMED, $request->fresh()->status);
    }

    private function makeRequest(?string $number, ?float $amount): IntakeRequest
    {
        $branch = Branch::query()->where('name', 'Central Branch')->firstOrFail();
        $draw = Draw::query()->where('name', '7:00 pm')->firstOrFail();

        return IntakeRequest::create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'draw_id' => $draw->id,
            'detected_number' => $number,
            'detected_amount' => $amount,
            'raw_text' => 'hola quiero jugar',
            'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
        ])->fresh();
    }
}
