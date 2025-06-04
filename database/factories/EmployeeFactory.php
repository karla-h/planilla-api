<?php

namespace Database\Factories;

use App\Models\Affiliation;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'firstname' => $this->faker->firstName,
            'lastname' => $this->faker->lastName,
            'dni' => $this->faker->unique()->numerify('########'),
            'born_date' => $this->faker->date(),
            'email' => $this->faker->unique()->safeEmail,
            'account' => $this->faker->bankAccountNumber,
            'phone' => $this->faker->phoneNumber,
            'address' => $this->faker->address,
            'headquarter_id' => \App\Models\Headquarter::inRandomOrder()->first()->id,
        ];
    }

     /**
     * Define una relación para crear un contrato asociado a un empleado.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function withContract()
    {
        return $this->afterCreating(function (Employee $employee) {
            Contract::factory()->create([
                'employee_id' => $employee->id,
                'accounting_salary' => 1130,
                'real_salary' => 1600,
            ]);
        });
    }

    /**
     * Define una relación para crear afiliaciones asociadas a un empleado.
     *
     * @param int $affiliationCount Número de afiliaciones a crear (por defecto 3)
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function withAffiliations()
    {
        $affiliationCount = $this->faker->numberBetween(1,3);
        return $this->afterCreating(function (Employee $employee) use ($affiliationCount) {
            
            $availableAffiliations = Affiliation::inRandomOrder()->take($affiliationCount)->get();

            foreach ($availableAffiliations as $affiliation) {
                EmployeeAffiliation::create([
                    'employee_id' => $employee->id,
                    'affiliation_id' => $affiliation->id,
                    'percent' => $affiliation->percent
                ]);
            }
        });
    }
}
