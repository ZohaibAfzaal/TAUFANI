<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_record_a_settlement_with_optional_note(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob   = User::factory()->create(['name' => 'Bob']);
        $group = Group::factory()->create(['created_by' => $alice->id]);
        $group->members()->attach([$alice->id, $bob->id]);

        Volt::actingAs($alice)
            ->test('settle-view')
            ->set('selectedGroupId', $group->id)
            ->set('fromId', $bob->id)
            ->set('toId', $alice->id)
            ->set('amount', 150)
            ->set('note', 'cash via bank')
            ->call('saveSettlement')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('settlements', [
            'group_id' => $group->id,
            'from_id'  => $bob->id,
            'to_id'    => $alice->id,
            'note'     => 'cash via bank',
        ]);
    }

    public function test_settlement_requires_different_from_and_to(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $group = Group::factory()->create(['created_by' => $alice->id]);
        $group->members()->attach($alice->id);

        Volt::actingAs($alice)
            ->test('settle-view')
            ->set('selectedGroupId', $group->id)
            ->set('fromId', $alice->id)
            ->set('toId', $alice->id)
            ->set('amount', 100)
            ->call('saveSettlement')
            ->assertHasErrors(['fromId']);
    }

    public function test_settlement_amount_must_be_positive(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob   = User::factory()->create(['name' => 'Bob']);
        $group = Group::factory()->create(['created_by' => $alice->id]);
        $group->members()->attach([$alice->id, $bob->id]);

        Volt::actingAs($alice)
            ->test('settle-view')
            ->set('selectedGroupId', $group->id)
            ->set('fromId', $bob->id)
            ->set('toId', $alice->id)
            ->set('amount', 0)
            ->call('saveSettlement')
            ->assertHasErrors(['amount']);
    }

    public function test_participant_can_delete_their_settlement(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob   = User::factory()->create(['name' => 'Bob']);
        $group = Group::factory()->create(['created_by' => $alice->id]);
        $group->members()->attach([$alice->id, $bob->id]);

        $settlement = Settlement::factory()->create([
            'group_id' => $group->id,
            'from_id'  => $bob->id,
            'to_id'    => $alice->id,
            'amount'   => 100,
        ]);

        Volt::actingAs($alice)
            ->test('settle-view')
            ->set('selectedGroupId', $group->id)
            ->call('deleteSettlement', $settlement->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('settlements', ['id' => $settlement->id]);
    }

    public function test_outsider_cannot_delete_settlement(): void
    {
        $alice    = User::factory()->create(['name' => 'Alice']);
        $bob      = User::factory()->create(['name' => 'Bob']);
        $outsider = User::factory()->create(['name' => 'Outsider']);
        $group    = Group::factory()->create(['created_by' => $alice->id]);
        $group->members()->attach([$alice->id, $bob->id]);

        $settlement = Settlement::factory()->create([
            'group_id' => $group->id,
            'from_id'  => $bob->id,
            'to_id'    => $alice->id,
            'amount'   => 100,
        ]);

        Volt::actingAs($outsider)
            ->test('settle-view')
            ->set('selectedGroupId', $group->id)
            ->call('deleteSettlement', $settlement->id);

        // Settlement must still exist
        $this->assertDatabaseHas('settlements', ['id' => $settlement->id]);
    }
}
