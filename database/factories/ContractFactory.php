<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contract>
 */
class ContractFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hire_date' => $this->faker->date(),
            'termination_date' => $this->faker->optional()->date(),
            'termination_reason' => $this->faker->optional()->word(),
            'accounting_salary' => $this->faker->randomFloat(2, 1000, 5000),
            'real_salary' => $this->faker->randomFloat(2, 1000, 5000),
        ];
    }
}
