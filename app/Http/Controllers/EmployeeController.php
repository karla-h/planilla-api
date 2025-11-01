<?php

namespace App\Http\Controllers;

use App\Architecture\Application\Services\EmployeeService;
use App\Exceptions\EntityNotFoundException;
use App\Http\Requests\EmployeeRequest;
use Illuminate\Http\JsonResponse;

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

    public function store(EmployeeRequest $request): JsonResponse
    {
        try {
            $response = $this->service->create($request->validated());
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
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

    public function update(EmployeeRequest $request, string $dni): JsonResponse
    {
        try {
            $response = $this->service->edit($dni, $request->validated());
            return response()->json($response, $response['status']);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
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