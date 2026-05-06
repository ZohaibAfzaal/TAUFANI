<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'       => fake()->words(2, true),
            'emoji'      => 'users',
            'created_by' => User::factory(),
        ];
    }
}
