<?php

namespace App\Architecture\Application\Services;

use App\Models\Contract;
use App\Models\Employee;
use App\Exceptions\EntityNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContractService
{
    public function findAll()
    {
        try {
            return Contract::with(['employee' => function($query) {
                $query->select('id', 'firstname', 'lastname', 'dni');
            }])->get();
        } catch (\Exception $e) {
            Log::error('Error en ContractService@findAll: ' . $e->getMessage());
            throw $e;
        }
    }

    public function create(array $data)
    {
        DB::beginTransaction();
        try {
            Log::info('Creando contrato con datos:', $data);

            // Validar que el empleado existe
            if (!Employee::find($data['employee_id'])) {
                return [
                    'status' => 404,
                    'message' => 'Empleado no encontrado'
                ];
            }

            // Si se crea un nuevo contrato activo, desactivar otros contratos del empleado
            if (($data['status_code'] ?? 'active') === 'active') {
                Contract::where('employee_id', $data['employee_id'])
                    ->where('status_code', 'active')
                    ->update(['status_code' => 'terminated']);
            }

            $contract = Contract::create($data);
            
            DB::commit();

            Log::info('Contrato creado exitosamente ID: ' . $contract->id);

            return [
                'status' => 201,
                'message' => 'Contrato creado exitosamente',
                'data' => $contract->load(['employee' => function($query) {
                    $query->select('id', 'firstname', 'lastname', 'dni');
                }])
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en ContractService@create: ' . $e->getMessage());
            return [
                'status' => 500,
                'message' => 'Error al crear contrato: ' . $e->getMessage()
            ];
        }
    }

    public function findBy($id)
    {
        try {
            Log::info('Buscando contrato ID: ' . $id);
            $contract = Contract::with(['employee' => function($query) {
                $query->select('id', 'firstname', 'lastname', 'dni');
            }])->find($id);
            
            if (!$contract) {
                throw new EntityNotFoundException('Contrato no encontrado');
            }
            
            return $contract;
        } catch (EntityNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error en ContractService@findBy: ' . $e->getMessage());
            throw $e;
        }
    }

    public function edit($id, array $data)
    {
        DB::beginTransaction();
        try {
            Log::info('Actualizando contrato ID: ' . $id, $data);

            $contract = $this->findBy($id);
            
            // Si se está activando este contrato, desactivar otros
            if (isset($data['status_code']) && $data['status_code'] === 'active') {
                Contract::where('employee_id', $contract->employee_id)
                    ->where('id', '!=', $id)
                    ->where('status_code', 'active')
                    ->update(['status_code' => 'terminated']);
            }

            $contract->update($data);
            
            DB::commit();

            Log::info('Contrato actualizado exitosamente ID: ' . $id);

            return [
                'status' => 200,
                'message' => 'Contrato actualizado exitosamente'
            ];

        } catch (EntityNotFoundException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en ContractService@edit: ' . $e->getMessage());
            return [
                'status' => 500,
                'message' => 'Error al actualizar contrato: ' . $e->getMessage()
            ];
        }
    }

    public function delete($id)
    {
        DB::beginTransaction();
        try {
            Log::info('Eliminando contrato ID: ' . $id);

            $contract = $this->findBy($id);
            $contract->delete();
            
            DB::commit();

            Log::info('Contrato eliminado exitosamente ID: ' . $id);

            return [
                'status' => 200,
                'message' => 'Contrato eliminado exitosamente'
            ];

        } catch (EntityNotFoundException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en ContractService@delete: ' . $e->getMessage());
            return [
                'status' => 500,
                'message' => 'Error al eliminar contrato: ' . $e->getMessage()
            ];
        }
    }

    public function terminateContract($id, $reason = null)
    {
        DB::beginTransaction();
        try {
            Log::info('Terminando contrato ID: ' . $id . ' con razón: ' . $reason);

            $contract = $this->findBy($id);
            
            $contract->update([
                'termination_date' => now(),
                'termination_reason' => $reason,
                'status_code' => 'terminated'
            ]);
            
            DB::commit();

            Log::info('Contrato terminado exitosamente ID: ' . $id);

            return [
                'status' => 200,
                'message' => 'Contrato terminado exitosamente'
            ];

        } catch (EntityNotFoundException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en ContractService@terminateContract: ' . $e->getMessage());
            return [
                'status' => 500,
                'message' => 'Error al terminar contrato: ' . $e->getMessage()
            ];
        }
    }

    public function getActiveContractByEmployee($employeeId)
    {
        try {
            Log::info('Buscando contrato activo para empleado ID: ' . $employeeId);

            // Verificar que el empleado existe
            if (!Employee::find($employeeId)) {
                throw new EntityNotFoundException('Empleado no encontrado');
            }

            $contract = Contract::where('employee_id', $employeeId)
                ->where('status_code', 'active')
                ->with(['employee' => function($query) {
                    $query->select('id', 'firstname', 'lastname', 'dni');
                }])
                ->first();

            if (!$contract) {
                throw new EntityNotFoundException('No se encontró contrato activo para este empleado');
            }

            return $contract;

        } catch (EntityNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error en ContractService@getActiveContractByEmployee: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getEmployeeContracts($employeeId)
    {
        try {
            Log::info('Buscando todos los contratos para empleado ID: ' . $employeeId);

            // Verificar que el empleado existe
            if (!Employee::find($employeeId)) {
                throw new EntityNotFoundException('Empleado no encontrado');
            }

            return Contract::where('employee_id', $employeeId)
                ->with(['employee' => function($query) {
                    $query->select('id', 'firstname', 'lastname', 'dni');
                }])
                ->orderBy('hire_date', 'desc')
                ->get();

        } catch (EntityNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error en ContractService@getEmployeeContracts: ' . $e->getMessage());
            throw $e;
        }
    }

    public function suspendContract($id)
    {
        DB::beginTransaction();
        try {
            Log::info('Suspendiendo contrato ID: ' . $id);

            $contract = $this->findBy($id);
            $contract->update(['status_code' => 'suspended']);
            
            DB::commit();

            Log::info('Contrato suspendido exitosamente ID: ' . $id);

            return [
                'status' => 200,
                'message' => 'Contrato suspendido exitosamente'
            ];

        } catch (EntityNotFoundException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en ContractService@suspendContract: ' . $e->getMessage());
            return [
                'status' => 500,
                'message' => 'Error al suspender contrato: ' . $e->getMessage()
            ];
        }
    }

    public function activateContract($id)
    {
        DB::beginTransaction();
        try {
            Log::info('Activando contrato ID: ' . $id);

            $contract = $this->findBy($id);
            
            // Desactivar otros contratos activos del mismo empleado
            Contract::where('employee_id', $contract->employee_id)
                ->where('id', '!=', $id)
                ->where('status_code', 'active')
                ->update(['status_code' => 'terminated']);

            $contract->update(['status_code' => 'active']);
            
            DB::commit();

            Log::info('Contrato activado exitosamente ID: ' . $id);

            return [
                'status' => 200,
                'message' => 'Contrato activado exitosamente'
            ];

        } catch (EntityNotFoundException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en ContractService@activateContract: ' . $e->getMessage());
            return [
                'status' => 500,
                'message' => 'Error al activar contrato: ' . $e->getMessage()
            ];
        }
    }
}