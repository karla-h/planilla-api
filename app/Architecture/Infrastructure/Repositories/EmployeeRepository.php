<?php

namespace App\Architecture\Infrastructure\Repositories;

use App\Architecture\Application\Dto\CreateEmployeeDto;
use App\Models\Employee;
use App\Exceptions\EntityNotFoundException;
use App\Models\Contract;
use App\Models\EmployeeAffiliation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeRepository
{
    public function findAll()
    {
        try {
            return Employee::with(['headquarter', 'contracts'])->get();
        } catch (\Exception $e) {
            Log::error('Error en findAll con contracts: ' . $e->getMessage());
            throw new \Exception('Error al obtener empleados');
        }
    }

    public function findBy($dni)
    {
        try {
            Log::info('Buscando empleado con DNI: ' . $dni);

            $employee = Employee::with([
                'headquarter',
                'contracts',
                'employeeAffiliations'
            ])->where('dni', $dni)->first();

            Log::info('Resultado de la búsqueda:', ['employee' => $employee ? 'Encontrado' : 'No encontrado']);

            if (!$employee) {
                throw new EntityNotFoundException('Empleado no encontrado');
            }

            return $employee;
        } catch (EntityNotFoundException $e) {
            Log::error('Empleado no encontrado: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error en findBy: ' . $e->getMessage());
            throw new \Exception('Error al obtener empleado: ' . $e->getMessage());
        }
    }

    // Mantén el método create igual
    public function create(CreateEmployeeDto $data)
    {
        DB::beginTransaction();
        try {
            if (Employee::where('dni', $data->dni)->exists()) {
                return [
                    'status' => 422,
                    'message' => 'El DNI ya está registrado'
                ];
            }

            if (!empty($data->email) && Employee::where('email', $data->email)->exists()) {
                return [
                    'status' => 422,
                    'message' => 'El email ya está registrado'
                ];
            }

            $employee = Employee::create([
                'firstname' => $data->firstname,
                'lastname' => $data->lastname,
                'dni' => $data->dni,
                'born_date' => $data->born_date,
                'email' => $data->email ?? null,
                'phone' => $data->phone ?? null,
                'address' => $data->address ?? null,
                'account' => $data->account ?? null,
                'headquarter_id' => $data->headquarter_id
            ]);

            if (!empty($data->affiliations)) {
                foreach ($data->affiliations as $affiliation) {
                    EmployeeAffiliation::create([
                        'employee_id' => $employee->id,
                        'affiliation_id' => $affiliation->affiliation_id,
                        'percent' => $affiliation->percent,
                    ]);
                }
            }

            if (!empty($data->contracts)) {
                Contract::create([
                    'employee_id' => $employee->id,
                    'hire_date' => $data->contracts->hire_date,
                    'accounting_salary' => $data->contracts->accounting_salary,
                    'real_salary' => $data->contracts->real_salary,
                    'payment_type' => $data->contracts->payment_type,
                    'status_code' => $data->contracts->status_code,
                ]);
            }

            DB::commit();

            return [
                'status' => 201,
                'message' => 'Empleado creado exitosamente',
                'data' => $employee->load('headquarter', 'contracts', 'affiliations')
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'status' => 500,
                'message' => 'Error al crear empleado: ' . $e->getMessage()
            ];
        }
    }

    public function edit(string $dni, CreateEmployeeDto $data)
{
    DB::beginTransaction();
    try {
        Log::info('Iniciando edición para DNI: ' . $dni);
        Log::info('Datos recibidos:', (array) $data);

        $employee = $this->findBy($dni);
        Log::info('Empleado encontrado ID: ' . $employee->id);

        if ($data->dni !== $dni && Employee::where('dni', $data->dni)->exists()) {
            Log::warning('DNI duplicado detectado');
            return [
                'status' => 422,
                'message' => 'El DNI ya está registrado'
            ];
        }

        Log::info('Actualizando datos básicos del empleado');
        $employee->update([
            'firstname' => $data->firstname,
            'lastname' => $data->lastname,
            'dni' => $data->dni,
            'born_date' => $data->born_date,
            'email' => $data->email ?? null,
            'phone' => $data->phone ?? null,
            'address' => $data->address ?? null,
            'account' => $data->account ?? null,
            'headquarter_id' => $data->headquarter_id
        ]);

        Log::info('Datos básicos actualizados');

        if (!empty($data->affiliations)) {
            Log::info('Procesando afiliaciones: ' . count($data->affiliations));
            EmployeeAffiliation::where('employee_id', $employee->id)->delete();

            foreach ($data->affiliations as $affiliation) {
                EmployeeAffiliation::create([
                    'employee_id' => $employee->id,
                    'affiliation_id' => $affiliation->affiliation_id,
                    'percent' => $affiliation->percent,
                ]);
            }
            Log::info('Afiliaciones actualizadas');
        }

        if (!empty($data->contracts)) {
            Log::info('Procesando contrato');
            $contract = Contract::where('employee_id', $employee->id)->first();

            if ($contract) {
                Log::info('Actualizando contrato existente ID: ' . $contract->id);
                $contract->update([
                    'hire_date' => $data->contracts->hire_date,
                    'accounting_salary' => $data->contracts->accounting_salary,
                    'real_salary' => $data->contracts->real_salary,
                    'payment_type' => $data->contracts->payment_type,
                    'status_code' => $data->contracts->status_code,
                ]);
            } else {
                Log::info('Creando nuevo contrato');
                Contract::create([
                    'employee_id' => $employee->id,
                    'hire_date' => $data->contracts->hire_date,
                    'accounting_salary' => $data->contracts->accounting_salary,
                    'real_salary' => $data->contracts->real_salary,
                    'payment_type' => $data->contracts->payment_type,
                    'status_code' => $data->contracts->status_code,
                ]);
            }
            Log::info('Contrato procesado');
        }

        DB::commit();
        Log::info('Edición completada exitosamente');

        return [
            'status' => 200,
            'message' => 'Empleado actualizado exitosamente',
            'data' => $employee->fresh(['headquarter', 'contracts', 'employeeAffiliations'])
        ];

    } catch (EntityNotFoundException $e) {
        DB::rollBack();
        Log::error('Empleado no encontrado en edit: ' . $e->getMessage());
        throw $e;
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error en edit: ' . $e->getMessage());
        Log::error('File: ' . $e->getFile());
        Log::error('Line: ' . $e->getLine());
        Log::error('Trace: ' . $e->getTraceAsString());
        return [
            'status' => 500,
            'message' => 'Error al actualizar empleado: ' . $e->getMessage()
        ];
    }
}

    // Métodos adicionales simplificados
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
            return [
                'status' => 500,
                'message' => 'Error al eliminar empleado'
            ];
        }
    }

    public function getEmployeesWithoutPayroll()
    {
        try {
            $employees = Employee::with('headquarter')->get();
            return [
                'status' => 200,
                'data' => $employees
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error al obtener empleados sin planilla'
            ];
        }
    }

    public function getEmployeesByBirthday($birth)
    {
        try {
            $employees = Employee::with('headquarter')->get();
            return [
                'status' => 200,
                'data' => $employees
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error al obtener empleados por cumpleaños'
            ];
        }
    }
}
