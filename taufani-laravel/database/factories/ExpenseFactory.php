<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'group_id'    => Group::factory(),
            'description' => fake()->words(3, true),
            'amount'      => fake()->randomFloat(2, 10, 5000),
            'paid_by'     => User::factory(),
            'split_type'  => 'equal',
            'date'        => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
        ];
    }
}
