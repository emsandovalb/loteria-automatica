<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_owner_can_create_a_seller_for_a_branch(): void
    {
        $owner = $this->user('owner@local.test');
        $branch = Branch::query()->where('name', 'North Branch')->firstOrFail();

        $this->actingAs($owner)->post(route('users.store'), [
            'name' => 'Nuevo Vendedor',
            'email' => 'nuevo@local.test',
            'role' => User::ROLE_SELLER,
            'branch_id' => $branch->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('users.index'));

        $created = $this->user('nuevo@local.test');
        $this->assertSame(User::ROLE_SELLER, $created->role);
        $this->assertSame($branch->id, $created->branch_id);
        $this->assertSame($owner->organization_id, $created->organization_id);
        $this->assertTrue($created->is_active);

        $this->post(route('logout'));
        $this->post('/login', ['email' => 'nuevo@local.test', 'password' => 'password123']);
        $this->assertAuthenticatedAs($created);
    }

    public function test_seller_requires_a_branch_and_other_roles_drop_it(): void
    {
        $owner = $this->user('owner@local.test');
        $branch = Branch::query()->firstOrFail();

        $this->actingAs($owner)->post(route('users.store'), [
            'name' => 'Sin Sucursal',
            'email' => 'sin@local.test',
            'role' => User::ROLE_SELLER,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('branch_id');

        $this->actingAs($owner)->post(route('users.store'), [
            'name' => 'Consulta',
            'email' => 'consulta@local.test',
            'role' => User::ROLE_VIEWER,
            'branch_id' => $branch->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('users.index'));

        $this->assertNull($this->user('consulta@local.test')->branch_id);
    }

    public function test_branch_from_another_organization_is_rejected(): void
    {
        $owner = $this->user('owner@local.test');
        $otherOrgBranch = Branch::create([
            'organization_id' => \App\Models\Organization::create(['name' => 'Otra', 'status' => 'active'])->id,
            'name' => 'Ajena',
            'channel_type' => Branch::CHANNEL_TYPE_SIMULATED,
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $this->actingAs($owner)->post(route('users.store'), [
            'name' => 'Intruso',
            'email' => 'intruso@local.test',
            'role' => User::ROLE_SELLER,
            'branch_id' => $otherOrgBranch->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('branch_id');
    }

    public function test_admin_manages_branch_staff_but_not_admins_or_owners(): void
    {
        $admin = $this->user('admin@local.test');

        $this->actingAs($admin)->get(route('users.index'))->assertOk();
        $this->actingAs($admin)->get(route('users.edit', $this->user('seller-1@local.test')))->assertOk();
        $this->actingAs($admin)->get(route('users.edit', $this->user('owner@local.test')))->assertForbidden();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Otro Admin',
            'email' => 'otro-admin@local.test',
            'role' => User::ROLE_ADMIN,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('role');
    }

    public function test_sellers_and_viewers_cannot_manage_users(): void
    {
        foreach (['seller-1@local.test', 'viewer@local.test'] as $email) {
            $this->actingAs($this->user($email))->get(route('users.index'))->assertForbidden();
            $this->actingAs($this->user($email))->get(route('users.create'))->assertForbidden();
        }
    }

    public function test_nobody_edits_their_own_account_here(): void
    {
        $owner = $this->user('owner@local.test');

        $this->actingAs($owner)->get(route('users.edit', $owner))->assertForbidden();
    }

    public function test_deactivated_user_cannot_log_in_and_active_session_is_closed(): void
    {
        $owner = $this->user('owner@local.test');
        $seller = $this->user('seller-1@local.test');

        $this->actingAs($seller)->get(route('dashboard'))->assertOk();

        $this->actingAs($owner)->put(route('users.update', $seller), [
            'name' => $seller->name,
            'email' => $seller->email,
            'role' => User::ROLE_SELLER,
            'branch_id' => $seller->branch_id,
            'is_active' => '0',
        ])->assertRedirect(route('users.index'));

        $this->assertFalse($seller->fresh()->is_active);

        $this->actingAs($seller->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post('/login', ['email' => $seller->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_password_is_kept_when_left_empty(): void
    {
        $owner = $this->user('owner@local.test');
        $seller = $this->user('seller-1@local.test');
        $hash = $seller->password;

        $this->actingAs($owner)->put(route('users.update', $seller), [
            'name' => 'Vendedor Renombrado',
            'email' => $seller->email,
            'role' => User::ROLE_SELLER,
            'branch_id' => $seller->branch_id,
            'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $this->assertSame('Vendedor Renombrado', $seller->fresh()->name);
        $this->assertSame($hash, $seller->fresh()->password);
    }

    public function test_users_menu_is_only_shown_to_managers(): void
    {
        $this->actingAs($this->user('owner@local.test'))->get(route('dashboard'))->assertSee(route('users.index'));
        $this->actingAs($this->user('seller-1@local.test'))->get(route('dashboard'))->assertDontSee(route('users.index'));
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
