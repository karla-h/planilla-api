<?php

namespace App\Http\Controllers;

use App\Architecture\Application\Services\ContractService;
use App\Exceptions\EntityNotFoundException;
use App\Http\Requests\ContractRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function __construct(protected ContractService $service) {}

    public function index(): JsonResponse
    {
        try {
            $contracts = $this->service->findAll();
            return response()->json($contracts, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function store(ContractRequest $request): JsonResponse
    {
        try {
            $response = $this->service->create($request->validated());
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear contrato'
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $contract = $this->service->findBy($id);
            return response()->json($contract, 200);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener contrato'
            ], 500);
        }
    }

    public function update(ContractRequest $request, string $id): JsonResponse
    {
        try {
            $response = $this->service->edit($id, $request->validated());
            return response()->json($response, $response['status']);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar contrato'
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $response = $this->service->delete($id);
            return response()->json($response, $response['status']);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar contrato'
            ], 500);
        }
    }

    public function terminate(string $id, Request $request): JsonResponse
    {
        try {
            $response = $this->service->terminateContract($id, $request->input('reason'));
            return response()->json($response, $response['status']);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al terminar contrato'
            ], 500);
        }
    }

    public function suspend(string $id): JsonResponse
    {
        try {
            $response = $this->service->suspendContract($id);
            return response()->json($response, $response['status']);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al suspender contrato'
            ], 500);
        }
    }

    public function activate(string $id): JsonResponse
    {
        try {
            $response = $this->service->activateContract($id);
            return response()->json($response, $response['status']);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al activar contrato'
            ], 500);
        }
    }

    public function getActiveByEmployee(string $employeeId): JsonResponse
    {
        try {
            $contract = $this->service->getActiveContractByEmployee($employeeId);
            return response()->json($contract, 200);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener contrato activo'
            ], 500);
        }
    }

    public function getByEmployee(string $employeeId): JsonResponse
    {
        try {
            $contracts = $this->service->getEmployeeContracts($employeeId);
            return response()->json($contracts, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener contratos del empleado'
            ], 500);
        }
    }

public function suspendWithPeriods($id, Request $request)
{
    return $this->service->suspendWithPeriods($id, $request->input('suspension_periods'));
}

public function reactivate($id)
{
    return $this->service->reactivateContract($id);
}
}