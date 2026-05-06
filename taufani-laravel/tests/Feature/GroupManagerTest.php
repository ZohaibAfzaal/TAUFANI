<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class GroupManagerTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────
    // createGroup
    // ──────────────────────────────────────────────

    public function test_authenticated_user_can_create_group(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)
            ->test('group-manager-view')
            ->set('name', 'Road Trip')
            ->set('emoji', '🚗')
            ->call('createGroup')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('groups', ['name' => 'Road Trip', 'created_by' => $user->id]);
        $this->assertDatabaseHas('group_user', ['user_id' => $user->id]);
    }

    public function test_group_name_must_be_at_least_3_chars(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)
            ->test('group-manager-view')
            ->set('name', 'AB')
            ->call('createGroup')
            ->assertHasErrors(['name']);
    }

    // ──────────────────────────────────────────────
    // addMember
    // ──────────────────────────────────────────────

    public function test_member_can_add_another_user_to_group(): void
    {
        $owner  = User::factory()->create();
        $newbie = User::factory()->create();
        $group  = Group::factory()->create(['created_by' => $owner->id]);
        $group->members()->attach($owner->id);

        Volt::actingAs($owner)
            ->test('group-manager-view')
            ->set('activeGroupId', $group->id)
            ->set('searchEmail', $newbie->email)
            ->call('addMember', $group->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('group_user', ['group_id' => $group->id, 'user_id' => $newbie->id]);
    }

    public function test_non_member_cannot_add_users_to_group(): void
    {
        $owner    = User::factory()->create();
        $outsider = User::factory()->create();
        $newbie   = User::factory()->create();
        $group    = Group::factory()->create(['created_by' => $owner->id]);
        $group->members()->attach($owner->id);

        Volt::actingAs($outsider)
            ->test('group-manager-view')
            ->set('searchEmail', $newbie->email)
            ->call('addMember', $group->id);

        // The newbie must NOT have been added to the group
        $this->assertDatabaseMissing('group_user', ['group_id' => $group->id, 'user_id' => $newbie->id]);
    }

    public function test_cannot_add_duplicate_member(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $group  = Group::factory()->create(['created_by' => $owner->id]);
        $group->members()->attach([$owner->id, $member->id]);

        Volt::actingAs($owner)
            ->test('group-manager-view')
            ->set('activeGroupId', $group->id)
            ->set('searchEmail', $member->email)
            ->call('addMember', $group->id)
            ->assertHasErrors(['searchEmail']);
    }

    // ──────────────────────────────────────────────
    // deleteGroup
    // ──────────────────────────────────────────────

    public function test_creator_can_delete_their_group(): void
    {
        $user  = User::factory()->create();
        $group = Group::factory()->create(['created_by' => $user->id]);
        $group->members()->attach($user->id);

        Volt::actingAs($user)
            ->test('group-manager-view')
            ->set('activeGroupId', $group->id)
            ->call('deleteGroup', $group->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
    }

    public function test_non_creator_cannot_delete_group(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $group  = Group::factory()->create(['created_by' => $owner->id]);
        $group->members()->attach([$owner->id, $member->id]);

        Volt::actingAs($member)
            ->test('group-manager-view')
            ->set('activeGroupId', $group->id)
            ->call('deleteGroup', $group->id);

        // Group must still exist
        $this->assertDatabaseHas('groups', ['id' => $group->id]);
    }

    // ──────────────────────────────────────────────
    // deleteExpense
    // ──────────────────────────────────────────────

    public function test_payer_can_delete_their_expense(): void
    {
        $payer = User::factory()->create();
        $other = User::factory()->create();
        $group = Group::factory()->create(['created_by' => $payer->id]);
        $group->members()->attach([$payer->id, $other->id]);

        $expense = Expense::factory()->create([
            'group_id' => $group->id,
            'paid_by'  => $payer->id,
        ]);

        Volt::actingAs($payer)
            ->test('group-manager-view')
            ->set('activeGroupId', $group->id)
            ->call('deleteExpense', $expense->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_regular_member_cannot_delete_others_expense(): void
    {
        $owner  = User::factory()->create();
        $payer  = User::factory()->create();
        $member = User::factory()->create();
        $group  = Group::factory()->create(['created_by' => $owner->id]);
        $group->members()->attach([$owner->id, $payer->id, $member->id]);

        $expense = Expense::factory()->create([
            'group_id' => $group->id,
            'paid_by'  => $payer->id,
        ]);

        Volt::actingAs($member)
            ->test('group-manager-view')
            ->set('activeGroupId', $group->id)
            ->call('deleteExpense', $expense->id);

        // Expense must still exist
        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    }

    // ──────────────────────────────────────────────
    // leaveGroup
    // ──────────────────────────────────────────────

    public function test_member_can_leave_group(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $group  = Group::factory()->create(['created_by' => $owner->id]);
        $group->members()->attach([$owner->id, $member->id]);

        Volt::actingAs($member)
            ->test('group-manager-view')
            ->set('activeGroupId', $group->id)
            ->call('leaveGroup', $group->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('group_user', ['group_id' => $group->id, 'user_id' => $member->id]);
    }

    public function test_creator_cannot_leave_group(): void
    {
        $owner = User::factory()->create();
        $group = Group::factory()->create(['created_by' => $owner->id]);
        $group->members()->attach($owner->id);

        Volt::actingAs($owner)
            ->test('group-manager-view')
            ->set('activeGroupId', $group->id)
            ->call('leaveGroup', $group->id)
            ->assertHasErrors(['leave']);

        $this->assertDatabaseHas('group_user', ['group_id' => $group->id, 'user_id' => $owner->id]);
    }
}
