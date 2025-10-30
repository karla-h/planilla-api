<?php

namespace App\Architecture\Infrastructure\Repositories;

use App\Models\Employee;
use App\Exceptions\EntityNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class EmployeeRepository
{
    public function create(array $data)
    {
        Log::info('=== EMPLOYEE REPOSITORY CREATE - INICIANDO ===', $data);
        
        DB::beginTransaction();
        try {
            Log::info('EmployeeRepository@create - Validando DNI único');
            
            // Verificar DNI único
            if (Employee::where('dni', $data['dni'])->exists()) {
                Log::warning('DNI ya existe', ['dni' => $data['dni']]);
                return [
                    'status' => 422,
                    'message' => 'El DNI ya está registrado'
                ];
            }

            // Verificar email único si se proporciona
            if (isset($data['email']) && Employee::where('email', $data['email'])->exists()) {
                Log::warning('Email ya existe', ['email' => $data['email']]);
                return [
                    'status' => 422,
                    'message' => 'El email ya está registrado'
                ];
            }

            Log::info('EmployeeRepository@create - Creando empleado');
            
            $employee = Employee::create([
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
                'dni' => $data['dni'],
                'born_date' => $data['born_date'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'account' => $data['account'] ?? null,
                'headquarter_id' => $data['headquarter_id'] // ✅ CORREGIDO
            ]);
            
            DB::commit();
            
            Log::info('EmployeeRepository@create - Empleado creado exitosamente', ['id' => $employee->id]);
            
            return [
                'status' => 201,
                'message' => 'Empleado creado exitosamente',
                'data' => $employee->load('headquarter')
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('=== ERROR EN EMPLOYEE REPOSITORY CREATE ===', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data
            ]);
            
            return [
                'status' => 500,
                'message' => 'Error al crear empleado: ' . $e->getMessage()
            ];
        }
    }

    public function findBy($dni)
    {
        try {
            $employee = Employee::with('headquarter')->where('dni', $dni)->first();
            
            if (!$employee) {
                throw new EntityNotFoundException('Empleado no encontrado');
            }
            
            return $employee;
        } catch (EntityNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error en EmployeeRepository@findBy', ['dni' => $dni, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function edit($dni, array $data)
    {
        DB::beginTransaction();
        try {
            $employee = $this->findBy($dni);

            // Validar unicidad de DNI si se está cambiando
            if (isset($data['dni']) && $data['dni'] !== $dni) {
                if (Employee::where('dni', $data['dni'])->exists()) {
                    return [
                        'status' => 422,
                        'message' => 'El DNI ya está registrado'
                    ];
                }
            }

            // Validar unicidad de email
            if (isset($data['email']) && $data['email'] !== $employee->email) {
                if (Employee::where('email', $data['email'])->exists()) {
                    return [
                        'status' => 422,
                        'message' => 'El email ya está registrado'
                    ];
                }
            }

            $employee->update($data);
            DB::commit();

            return [
                'status' => 200,
                'message' => 'Empleado actualizado exitosamente'
            ];

        } catch (EntityNotFoundException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en EmployeeRepository@edit', ['dni' => $dni, 'error' => $e->getMessage()]);
            return [
                'status' => 500,
                'message' => 'Error al actualizar empleado: ' . $e->getMessage()
            ];
        }
    }

    public function findAll()
    {
        try {
            return Employee::with('headquarter')->get();
        } catch (\Exception $e) {
            Log::error('Error en EmployeeRepository@findAll', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function delete($dni)
    {
        DB::beginTransaction();
        try {
            $employee = $this->findBy($dni);
            $employee->delete();
            DB::commit();

            return [
                'status' => 200,
                'message' => 'Empleado eliminado exitosamente'
            ];

        } catch (EntityNotFoundException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en EmployeeRepository@delete', ['dni' => $dni, 'error' => $e->getMessage()]);
            return [
                'status' => 500,
                'message' => 'Error al eliminar empleado: ' . $e->getMessage()
            ];
        }
    }

    public function getEmployeesWithoutPayroll()
    {
        try {
            // Implementar lógica para empleados sin planilla
            $employees = Employee::with('headquarter')->get();
            
            return [
                'status' => 200,
                'data' => $employees
            ];
        } catch (\Exception $e) {
            Log::error('Error en EmployeeRepository@getEmployeesWithoutPayroll', ['error' => $e->getMessage()]);
            return [
                'status' => 500,
                'message' => 'Error al obtener empleados sin planilla: ' . $e->getMessage()
            ];
        }
    }

    public function getEmployeesByBirthday($birth)
    {
        try {
            // Implementar lógica para buscar por cumpleaños
            $employees = Employee::with('headquarter')->get();
            
            return [
                'status' => 200,
                'data' => $employees
            ];
        } catch (\Exception $e) {
            Log::error('Error en EmployeeRepository@getEmployeesByBirthday', ['birth' => $birth, 'error' => $e->getMessage()]);
            return [
                'status' => 500,
                'message' => 'Error al obtener empleados por cumpleaños: ' . $e->getMessage()
            ];
        }
    }
}