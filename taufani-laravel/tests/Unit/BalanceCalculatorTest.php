<?php

namespace Tests\Unit;

use App\Models\Expense;
use App\Models\Group;
use App\Models\Settlement;
use App\Models\User;
use App\Services\BalanceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function makeGroup(User $creator, array $members = []): Group
    {
        $group = Group::factory()->create(['created_by' => $creator->id]);
        $group->members()->attach([$creator->id, ...$members]);
        return $group;
    }

    private function addExpense(Group $group, User $payer, float $amount, array $participantIds): Expense
    {
        $expense = Expense::factory()->create([
            'group_id'   => $group->id,
            'paid_by'    => $payer->id,
            'amount'     => $amount,
            'split_type' => 'equal',
        ]);

        $n      = count($participantIds);
        $attach = [];
        foreach ($participantIds as $pid) {
            $attach[$pid] = ['amount' => $amount / $n];
        }
        $expense->participants()->attach($attach);

        return $expense;
    }

    public function test_returns_empty_with_no_expenses(): void
    {
        $user  = User::factory()->create();
        $group = $this->makeGroup($user);

        $calc   = new BalanceCalculator();
        $result = $calc->calculate(collect([$group->id]));

        $this->assertEmpty($result);
    }

    public function test_calculates_equal_split_correctly(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob   = User::factory()->create(['name' => 'Bob']);
        $group = $this->makeGroup($alice, [$bob->id]);

        // Alice pays RS300, split equally — Bob owes Alice 150
        $this->addExpense($group, $alice, 300, [$alice->id, $bob->id]);

        $calc   = new BalanceCalculator();
        $result = $calc->calculate(collect([$group->id]));

        $this->assertCount(1, $result);
        $this->assertSame($bob->id, $result[0]['from']);
        $this->assertSame($alice->id, $result[0]['to']);
        $this->assertEqualsWithDelta(150, $result[0]['amount'], 0.01);
    }

    public function test_nets_out_debts_in_both_directions(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob   = User::factory()->create(['name' => 'Bob']);
        $group = $this->makeGroup($alice, [$bob->id]);

        // Alice pays RS300 → Bob owes Alice 150
        $this->addExpense($group, $alice, 300, [$alice->id, $bob->id]);
        // Bob pays RS200 → Alice owes Bob 100
        $this->addExpense($group, $bob, 200, [$alice->id, $bob->id]);

        $calc   = new BalanceCalculator();
        $result = $calc->calculate(collect([$group->id]));

        // Net: Bob still owes Alice 50
        $this->assertCount(1, $result);
        $this->assertSame($bob->id, $result[0]['from']);
        $this->assertSame($alice->id, $result[0]['to']);
        $this->assertEqualsWithDelta(50, $result[0]['amount'], 0.01);
    }

    public function test_zeroes_out_fully_settled_debts(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob   = User::factory()->create(['name' => 'Bob']);
        $group = $this->makeGroup($alice, [$bob->id]);

        $this->addExpense($group, $alice, 300, [$alice->id, $bob->id]);

        Settlement::factory()->create([
            'group_id' => $group->id,
            'from_id'  => $bob->id,
            'to_id'    => $alice->id,
            'amount'   => 150,
        ]);

        $calc   = new BalanceCalculator();
        $result = $calc->calculate(collect([$group->id]));

        $this->assertEmpty($result);
    }

    public function test_partial_settlement_reduces_debt(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob   = User::factory()->create(['name' => 'Bob']);
        $group = $this->makeGroup($alice, [$bob->id]);

        $this->addExpense($group, $alice, 300, [$alice->id, $bob->id]);

        Settlement::factory()->create([
            'group_id' => $group->id,
            'from_id'  => $bob->id,
            'to_id'    => $alice->id,
            'amount'   => 100,
        ]);

        $calc   = new BalanceCalculator();
        $result = $calc->calculate(collect([$group->id]));

        $this->assertCount(1, $result);
        $this->assertEqualsWithDelta(50, $result[0]['amount'], 0.01);
    }

    public function test_filters_to_specific_user(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob   = User::factory()->create(['name' => 'Bob']);
        $carol = User::factory()->create(['name' => 'Carol']);
        $group = $this->makeGroup($alice, [$bob->id, $carol->id]);

        $this->addExpense($group, $alice, 300, [$alice->id, $bob->id, $carol->id]);
        $this->addExpense($group, $bob, 120, [$alice->id, $bob->id, $carol->id]);

        $calc = new BalanceCalculator();

        $aliceView = $calc->calculate(collect([$group->id]), $alice->id);
        foreach ($aliceView as $r) {
            $this->assertTrue($r['from'] === $alice->id || $r['to'] === $alice->id);
        }

        $carolView = $calc->calculate(collect([$group->id]), $carol->id);
        foreach ($carolView as $r) {
            $this->assertTrue($r['from'] === $carol->id || $r['to'] === $carol->id);
        }
    }

    public function test_with_users_hydrates_user_objects(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob   = User::factory()->create(['name' => 'Bob']);
        $group = $this->makeGroup($alice, [$bob->id]);

        $this->addExpense($group, $alice, 300, [$alice->id, $bob->id]);

        $calc     = new BalanceCalculator();
        $balances = $calc->calculate(collect([$group->id]));
        $hydrated = $calc->withUsers($balances);

        $this->assertNotEmpty($hydrated);
        $this->assertArrayHasKey('fromUser', $hydrated[0]);
        $this->assertArrayHasKey('toUser', $hydrated[0]);
        $this->assertInstanceOf(User::class, $hydrated[0]['fromUser']);
        $this->assertInstanceOf(User::class, $hydrated[0]['toUser']);
    }

    public function test_aggregates_across_multiple_groups(): void
    {
        $alice  = User::factory()->create(['name' => 'Alice']);
        $bob    = User::factory()->create(['name' => 'Bob']);
        $group1 = $this->makeGroup($alice, [$bob->id]);
        $group2 = $this->makeGroup($alice, [$bob->id]);

        $this->addExpense($group1, $alice, 200, [$alice->id, $bob->id]);
        $this->addExpense($group2, $alice, 300, [$alice->id, $bob->id]);

        $calc   = new BalanceCalculator();
        $result = $calc->calculate(collect([$group1->id, $group2->id]));

        // Bob owes Alice 100 + 150 = 250 total
        $this->assertCount(1, $result);
        $this->assertEqualsWithDelta(250, $result[0]['amount'], 0.01);
    }

    public function test_ignores_near_zero_balances(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob   = User::factory()->create(['name' => 'Bob']);
        $group = $this->makeGroup($alice, [$bob->id]);

        $expense = Expense::factory()->create([
            'group_id'   => $group->id,
            'paid_by'    => $alice->id,
            'amount'     => 0.01,
            'split_type' => 'equal',
        ]);
        $expense->participants()->attach([
            $alice->id => ['amount' => 0.005],
            $bob->id   => ['amount' => 0.005],
        ]);

        $calc   = new BalanceCalculator();
        $result = $calc->calculate(collect([$group->id]));

        $this->assertEmpty($result);
    }
}
