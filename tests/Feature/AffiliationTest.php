<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Affiliation;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AffiliationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_assign_affiliation_to_employee()
    {
        $employee = Employee::factory()->create();
        $affiliation = Affiliation::factory()->create();

        $assignmentData = [
            'employee_id' => $employee->id,
            'affiliation_id' => $affiliation->id,
            'percent' => 15.5
        ];

        $response = $this->postJson('/api/affiliations/assign-to-employee', $assignmentData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Afiliación asignada al empleado exitosamente'
                ]);

        $this->assertDatabaseHas('employee_affiliations', [
            'employee_id' => $employee->id,
            'affiliation_id' => $affiliation->id,
            'percent' => 15.5
        ]);
    }

    /** @test */
    public function it_validates_percent_range_when_assigning_affiliation()
    {
        $employee = Employee::factory()->create();
        $affiliation = Affiliation::factory()->create();

        $assignmentData = [
            'employee_id' => $employee->id,
            'affiliation_id' => $affiliation->id,
            'percent' => 150
        ];

        $response = $this->postJson('/api/affiliations/assign-to-employee', $assignmentData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['percent']);
    }

    /** @test */
    public function it_cannot_delete_affiliation_with_employees()
    {
        $affiliation = Affiliation::factory()->create();
        $employee = Employee::factory()->create();
        
        EmployeeAffiliation::create([
            'employee_id' => $employee->id,
            'affiliation_id' => $affiliation->id,
            'percent' => 10.0
        ]);

        $response = $this->deleteJson("/api/affiliations/{$affiliation->id}");

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'No se puede eliminar la afiliación porque tiene empleados asociados'
                ]);
    }
}