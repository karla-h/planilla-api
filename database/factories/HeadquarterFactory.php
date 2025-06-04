<?php

namespace Database\Factories;

use App\Models\Headquarter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Headquarter>
 */
class HeadquarterFactory extends Factory
{
    protected $model = Headquarter::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            'address' => $this->faker->address,
        ];
    }
}
