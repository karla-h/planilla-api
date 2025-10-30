<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Employee;
use App\Models\Headquarters;
use App\Models\Affiliation;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_employee_with_contract_and_affiliations()
    {
        $headquarter = Headquarters::factory()->create();
        $affiliation1 = Affiliation::factory()->create();
        $affiliation2 = Affiliation::factory()->create();

        $employeeData = [
            'firstname' => 'Juan',
            'lastname' => 'Pérez',
            'dni' => '12345678',
            'born_date' => '1990-01-15',
            'email' => 'juan@test.com',
            'phone' => '987654321',
            'headquarter_id' => $headquarter->id,
            'contract' => [
                'hire_date' => '2024-01-01',
                'accounting_salary' => 2500.00,
                'real_salary' => 2000.00,
                'payment_method' => 'monthly',
                'status_code' => 'enable'
            ],
            'affiliations' => [
                [
                    'affiliation_id' => $affiliation1->id,
                    'percent' => 13.0
                ],
                [
                    'affiliation_id' => $affiliation2->id,
                    'percent' => 10.0
                ]
            ]
        ];

        $response = $this->postJson('/api/employees', $employeeData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Empleado creado exitosamente con contrato y afiliaciones'
                ]);

        $this->assertDatabaseHas('employees', [
            'dni' => '12345678',
            'email' => 'juan@test.com'
        ]);

        $this->assertDatabaseHas('contracts', [
            'accounting_salary' => 2500.00,
            'payment_method' => 'monthly'
        ]);

        $this->assertDatabaseCount('employee_affiliations', 2);
    }

    /** @test */
    public function it_validates_unique_dni_when_creating_employee()
    {
        Employee::factory()->create(['dni' => '87654321']);

        $data = [
            'firstname' => 'Test',
            'lastname' => 'User',
            'dni' => '87654321',
            'born_date' => '1990-01-01',
            'headquarter_id' => Headquarters::factory()->create()->id
        ];

        $response = $this->postJson('/api/employees', $data);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['dni']);
    }

    /** @test */
    public function it_can_list_employees_filtered_by_headquarter()
    {
        $headquarter1 = Headquarters::factory()->create();
        $headquarter2 = Headquarters::factory()->create();

        Employee::factory()->count(2)->create(['headquarter_id' => $headquarter1->id]);
        Employee::factory()->create(['headquarter_id' => $headquarter2->id]);

        $response = $this->getJson("/api/employees?headquarter_id={$headquarter1->id}");

        $response->assertStatus(200)
                ->assertJsonCount(2, 'data');
    }
}