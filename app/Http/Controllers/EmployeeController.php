<?php

namespace App\Http\Controllers;

use App\Architecture\Application\Dto\CreateEmployeeDto;
use App\Architecture\Application\Services\EmployeeService;
use App\Exceptions\EntityNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmployeeController extends Controller
{
    public function __construct(protected EmployeeService $service) {}

    public function index(): JsonResponse
    {
        try {
            $employees = $this->service->findAll();
            return response()->json($employees, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener empleados'
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $dto = $request->all();
            $response = $this->service->create(CreateEmployeeDto::from($dto));
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            Log::error('Error al crear empleado: ' . $e->__toString());
            return response()->json([
                'message' => 'Error al crear empleado'
            ], 500);
        }
    }

    public function show(string $dni): JsonResponse
    {
        try {
            $employee = $this->service->findBy($dni);
            return response()->json($employee, 200);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener empleado'
            ], 500);
        }
    }

    public function update(Request $request, string $dni): JsonResponse
    {
        try {
            $dto = $request->all();
            $response = $this->service->edit($dni, CreateEmployeeDto::from($dto));
            return response()->json($response, $response['status']);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->__toString()], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar empleado'
            ], 500);
        }
    }

    public function destroy(string $dni): JsonResponse
    {
        try {
            $response = $this->service->delete($dni);
            return response()->json($response, $response['status']);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar empleado'
            ], 500);
        }
    }

    public function getEmployeesWithoutPayroll(): JsonResponse
    {
        try {
            $response = $this->service->getEmployeesWithoutPayroll();
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener empleados sin planilla'
            ], 500);
        }
    }

    public function getEmployeesByBirthday(string $birth): JsonResponse
    {
        try {
            $response = $this->service->getEmployeesByBirthday($birth);
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener empleados por cumpleaños'
            ], 500);
        }
    }
}