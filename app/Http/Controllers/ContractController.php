<?php

namespace App\Http\Controllers;

use App\Architecture\Application\Services\ContractService;
use App\Exceptions\EntityNotFoundException;
use App\Http\Requests\ContractRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContractController extends Controller
{
    public function __construct(protected ContractService $service) {}

    public function index(): JsonResponse
    {
        try {
            Log::info('Obteniendo todos los contratos');
            $contracts = $this->service->findAll();
            return response()->json($contracts, 200);
        } catch (\Exception $e) {
            Log::error('Error en ContractController@index: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function store(ContractRequest $request): JsonResponse
    {
        try {
            Log::info('Creando nuevo contrato', $request->all());
            $response = $this->service->create($request->validated());
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            Log::error('Error en ContractController@store: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al crear contrato: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            Log::info('Buscando contrato ID: ' . $id);
            $contract = $this->service->findBy($id);
            return response()->json($contract, 200);
        } catch (EntityNotFoundException $e) {
            Log::warning('Contrato no encontrado: ' . $id);
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Error en ContractController@show: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener contrato'
            ], 500);
        }
    }

    public function update(ContractRequest $request, string $id): JsonResponse
    {
        try {
            Log::info('Actualizando contrato ID: ' . $id, $request->all());
            $response = $this->service->edit($id, $request->validated());
            return response()->json($response, $response['status']);
        } catch (EntityNotFoundException $e) {
            Log::warning('Contrato no encontrado para actualizar: ' . $id);
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Error en ContractController@update: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al actualizar contrato: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            Log::info('Eliminando contrato ID: ' . $id);
            $response = $this->service->delete($id);
            return response()->json($response, $response['status']);
        } catch (EntityNotFoundException $e) {
            Log::warning('Contrato no encontrado para eliminar: ' . $id);
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Error en ContractController@destroy: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al eliminar contrato'
            ], 500);
        }
    }

    public function terminate(string $id, Request $request): JsonResponse
    {
        try {
            Log::info('Terminando contrato ID: ' . $id);
            $response = $this->service->terminateContract($id, $request->input('reason'));
            return response()->json($response, $response['status']);
        } catch (EntityNotFoundException $e) {
            Log::warning('Contrato no encontrado para terminar: ' . $id);
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Error en ContractController@terminate: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al terminar contrato: ' . $e->getMessage()
            ], 500);
        }
    }

    public function suspend(string $id): JsonResponse
    {
        try {
            Log::info('Suspendiendo contrato ID: ' . $id);
            $response = $this->service->suspendContract($id);
            return response()->json($response, $response['status']);
        } catch (EntityNotFoundException $e) {
            Log::warning('Contrato no encontrado para suspender: ' . $id);
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Error en ContractController@suspend: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al suspender contrato: ' . $e->getMessage()
            ], 500);
        }
    }

    public function activate(string $id): JsonResponse
    {
        try {
            Log::info('Activando contrato ID: ' . $id);
            $response = $this->service->activateContract($id);
            return response()->json($response, $response['status']);
        } catch (EntityNotFoundException $e) {
            Log::warning('Contrato no encontrado para activar: ' . $id);
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Error en ContractController@activate: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al activar contrato: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getActiveByEmployee(string $employeeId): JsonResponse
    {
        try {
            Log::info('Buscando contrato activo para empleado ID: ' . $employeeId);
            $contract = $this->service->getActiveContractByEmployee($employeeId);
            return response()->json($contract, 200);
        } catch (EntityNotFoundException $e) {
            Log::warning('No se encontró contrato activo para empleado: ' . $employeeId);
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Error en ContractController@getActiveByEmployee: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener contrato activo'
            ], 500);
        }
    }

    public function getByEmployee(string $employeeId): JsonResponse
    {
        try {
            Log::info('Buscando todos los contratos para empleado ID: ' . $employeeId);
            $contracts = $this->service->getEmployeeContracts($employeeId);
            return response()->json($contracts, 200);
        } catch (\Exception $e) {
            Log::error('Error en ContractController@getByEmployee: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener contratos del empleado'
            ], 500);
        }
    }
}