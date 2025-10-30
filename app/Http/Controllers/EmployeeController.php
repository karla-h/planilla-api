<?php

namespace App\Http\Controllers;

use App\Architecture\Application\Services\EmployeeService;
use App\Exceptions\EntityNotFoundException;
use App\Http\Requests\EmployeeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class EmployeeController extends Controller
{
    public function __construct(protected EmployeeService $service) {}

    public function index(): JsonResponse
    {
        try {
            Log::info('EmployeeController@index - Obteniendo empleados');
            $employees = $this->service->findAll();
            return response()->json($employees, 200);
        } catch (\Exception $e) {
            Log::error('Error en EmployeeController@index', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Error al obtener empleados: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(EmployeeRequest $request): JsonResponse
    {
        try {
            Log::info('EmployeeController@store - Creando empleado', $request->validated());
            // Pasar los datos validados al service
            $response = $this->service->create($request->validated());
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            Log::error('Error en EmployeeController@store', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Error al crear empleado: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(string $dni): JsonResponse
    {
        try {
            Log::info('EmployeeController@show - Buscando empleado', ['dni' => $dni]);
            $employee = $this->service->findBy($dni);
            return response()->json($employee, 200);
        } catch (EntityNotFoundException $e) {
            Log::warning('Empleado no encontrado', ['dni' => $dni]);
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Error en EmployeeController@show', [
                'error' => $e->getMessage(),
                'dni' => $dni
            ]);
            return response()->json([
                'message' => 'Error al obtener empleado: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(EmployeeRequest $request, string $dni): JsonResponse
    {
        try {
            Log::info('EmployeeController@update - Actualizando empleado', [
                'dni' => $dni,
                'data' => $request->validated()
            ]);
            // Pasar los datos validados al service
            $response = $this->service->edit($dni, $request->validated());
            return response()->json($response, $response['status']);
        } catch (EntityNotFoundException $e) {
            Log::warning('Empleado no encontrado para actualizar', ['dni' => $dni]);
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Error en EmployeeController@update', [
                'error' => $e->getMessage(),
                'dni' => $dni
            ]);
            return response()->json([
                'message' => 'Error al actualizar empleado: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $dni): JsonResponse
    {
        try {
            Log::info('EmployeeController@destroy - Eliminando empleado', ['dni' => $dni]);
            $response = $this->service->delete($dni);
            return response()->json($response, $response['status']);
        } catch (EntityNotFoundException $e) {
            Log::warning('Empleado no encontrado para eliminar', ['dni' => $dni]);
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Error en EmployeeController@destroy', [
                'error' => $e->getMessage(),
                'dni' => $dni
            ]);
            return response()->json([
                'message' => 'Error al eliminar empleado: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getEmployeesWithoutPayroll(): JsonResponse
    {
        try {
            Log::info('EmployeeController@getEmployeesWithoutPayroll - Obteniendo empleados sin planilla');
            $response = $this->service->getEmployeesWithoutPayroll();
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            Log::error('Error en EmployeeController@getEmployeesWithoutPayroll', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Error al obtener empleados sin planilla: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getEmployeesByBirthday(string $birth): JsonResponse
    {
        try {
            Log::info('EmployeeController@getEmployeesByBirthday - Obteniendo por cumpleaños', ['birth' => $birth]);
            $response = $this->service->getEmployeesByBirthday($birth);
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            Log::error('Error en EmployeeController@getEmployeesByBirthday', [
                'error' => $e->getMessage(),
                'birth' => $birth
            ]);
            return response()->json([
                'message' => 'Error al obtener empleados por cumpleaños: ' . $e->getMessage()
            ], 500);
        }
    }
}