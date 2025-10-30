<?php

namespace App\Architecture\Application\Services;

use App\Models\Employee;
use App\Exceptions\EntityNotFoundException;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    public function create(array $data)
    {
        try {
            DB::beginTransaction();

            // Validar que el DNI sea único
            if (Employee::where('dni', $data['dni'])->exists()) {
                return [
                    'status' => 422,
                    'message' => 'El DNI ya está registrado'
                ];
            }

            // Validar que el email sea único si se proporciona
            if (isset($data['email']) && Employee::where('email', $data['email'])->exists()) {
                return [
                    'status' => 422,
                    'message' => 'El email ya está registrado'
                ];
            }

            $employee = Employee::create($data);

            DB::commit();

            return [
                'status' => 201,
                'message' => 'Empleado creado exitosamente',
                'data' => $employee->load('headquarter')
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'status' => 500,
                'message' => 'Error al crear empleado: ' . $e->getMessage()
            ];
        }
    }

    public function findBy($dni)
    {
        $employee = Employee::with('headquarter')->where('dni', $dni)->first();
        
        if (!$employee) {
            throw new EntityNotFoundException('Empleado no encontrado');
        }
        
        return $employee;
    }

    public function edit($dni, array $data)
    {
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

            return [
                'status' => 200,
                'message' => 'Empleado actualizado exitosamente'
            ];

        } catch (EntityNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error al actualizar empleado: ' . $e->getMessage()
            ];
        }
    }

    public function findAll()
    {
        return Employee::with('headquarter')->get();
    }

    public function delete($dni)
    {
        try {
            $employee = $this->findBy($dni);
            $employee->delete();

            return [
                'status' => 200,
                'message' => 'Empleado eliminado exitosamente'
            ];

        } catch (EntityNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error al eliminar empleado: ' . $e->getMessage()
            ];
        }
    }

    public function getEmployeesWithoutPayroll()
    {
        // Implementar lógica para empleados sin planilla
        $employees = Employee::with('headquarter')->get();
        
        return [
            'status' => 200,
            'data' => $employees
        ];
    }

    public function getEmployeesByBirthday($birth)
    {
        // Implementar lógica para buscar por cumpleaños
        $employees = Employee::with('headquarter')->get();
        
        return [
            'status' => 200,
            'data' => $employees
        ];
    }
}