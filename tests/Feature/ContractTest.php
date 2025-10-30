<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Contract;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ContractTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_cannot_create_multiple_active_contracts_for_same_employee()
    {
        $employee = Employee::factory()->create();
        Contract::factory()->create([
            'employee_id' => $employee->id,
            'status_code' => 'enable'
        ]);

        $contractData = [
            'hire_date' => '2024-02-01',
            'accounting_salary' => 3000.00,
            'real_salary' => 2500.00,
            'payment_method' => 'monthly',
            'status_code' => 'enable',
            'employee_id' => $employee->id
        ];

        $response = $this->postJson('/api/contracts', $contractData);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'El empleado ya tiene un contrato activo'
                ]);
    }

    /** @test */
    public function it_can_terminate_contract()
    {
        $contract = Contract::factory()->create(['status_code' => 'enable']);

        $terminationData = [
            'termination_date' => '2024-12-31',
            'termination_reason' => 'Fin de contrato'
        ];

        $response = $this->postJson("/api/contracts/{$contract->id}/terminate", $terminationData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Contrato terminado exitosamente'
                ]);

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status_code' => 'disable',
            'termination_date' => '2024-12-31',
            'termination_reason' => 'Fin de contrato'
        ]);
    }
}