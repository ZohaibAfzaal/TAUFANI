<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettlementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'from_id'  => User::factory(),
            'to_id'    => User::factory(),
            'amount'   => fake()->randomFloat(2, 10, 1000),
            'date'     => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'note'     => null,
        ];
    }
}
