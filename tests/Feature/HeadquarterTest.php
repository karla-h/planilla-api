<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Headquarters;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HeadquarterTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_list_all_active_headquarters()
    {
        Headquarters::factory()->count(3)->create();
        Headquarters::factory()->create(['deleted_at' => now()]);

        $response = $this->getJson('/api/headquarters');

        $response->assertStatus(200)
                ->assertJsonCount(3, 'data')
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => ['id', 'name', 'address']
                    ]
                ]);
    }

    /** @test */
    public function it_can_create_a_headquarter()
    {
        $data = [
            'name' => 'Sede Central',
            'address' => 'Av. Principal 123'
        ];

        $response = $this->postJson('/api/headquarters', $data);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Sede creada exitosamente'
                ]);

        $this->assertDatabaseHas('headquarters', $data);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_headquarter()
    {
        $response = $this->postJson('/api/headquarters', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'address']);
    }

    /** @test */
    public function it_cannot_delete_headquarter_with_employees()
    {
        $headquarter = Headquarters::factory()->create();
        Employee::factory()->create(['headquarter_id' => $headquarter->id]);

        $response = $this->deleteJson("/api/headquarters/{$headquarter->id}");

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'No se puede eliminar la sede porque tiene empleados asociados'
                ]);
    }
}