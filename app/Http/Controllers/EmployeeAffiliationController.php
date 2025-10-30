<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAffiliation;
use App\Models\Employee;
use App\Models\Affiliation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmployeeAffiliationController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            Log::info('Obteniendo todas las afiliaciones de empleados');
            $employeeAffiliations = EmployeeAffiliation::with(['employee', 'affiliation'])->get();
            
            return response()->json([
                'status' => 200,
                'data' => $employeeAffiliations
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en EmployeeAffiliationController@index: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Error al obtener afiliaciones de empleados: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            Log::info('Creando afiliación para empleado', $request->all());
            
            $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'affiliation_id' => 'required|exists:affiliations,id',
                'percent' => 'required|numeric|min:0|max:100'
            ]);

            // Verificar si ya existe la relación
            $existing = EmployeeAffiliation::where('employee_id', $request->employee_id)
                ->where('affiliation_id', $request->affiliation_id)
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => 422,
                    'message' => 'El empleado ya tiene esta afiliación asignada'
                ], 422);
            }

            $employeeAffiliation = EmployeeAffiliation::create([
                'employee_id' => $request->employee_id,
                'affiliation_id' => $request->affiliation_id,
                'percent' => $request->percent
            ]);

            // Cargar relaciones
            $employeeAffiliation->load(['employee', 'affiliation']);

            return response()->json([
                'status' => 201,
                'message' => 'Afiliación asignada al empleado exitosamente',
                'data' => $employeeAffiliation
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Error de validación en EmployeeAffiliationController@store: ' . json_encode($e->errors()));
            return response()->json([
                'status' => 422,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error en EmployeeAffiliationController@store: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Error al asignar afiliación: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getByEmployee($employeeId): JsonResponse
    {
        try {
            Log::info('Buscando afiliaciones para empleado ID: ' . $employeeId);
            
            // Verificar que el empleado existe
            $employee = Employee::find($employeeId);
            if (!$employee) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Empleado no encontrado'
                ], 404);
            }

            $affiliations = EmployeeAffiliation::with(['affiliation'])
                ->where('employee_id', $employeeId)
                ->get();

            return response()->json([
                'status' => 200,
                'data' => $affiliations
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en EmployeeAffiliationController@getByEmployee: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Error al obtener afiliaciones: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            Log::info('Buscando afiliación de empleado ID: ' . $id);
            
            $employeeAffiliation = EmployeeAffiliation::with(['employee', 'affiliation'])->find($id);
            
            if (!$employeeAffiliation) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Afiliación de empleado no encontrada'
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data' => $employeeAffiliation
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en EmployeeAffiliationController@show: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Error al obtener afiliación de empleado: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            Log::info('Actualizando afiliación de empleado ID: ' . $id, $request->all());
            
            $request->validate([
                'percent' => 'required|numeric|min:0|max:100'
            ]);

            $employeeAffiliation = EmployeeAffiliation::find($id);
            
            if (!$employeeAffiliation) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Afiliación de empleado no encontrada'
                ], 404);
            }

            $employeeAffiliation->update([
                'percent' => $request->percent
            ]);

            // Cargar relaciones actualizadas
            $employeeAffiliation->load(['employee', 'affiliation']);

            return response()->json([
                'status' => 200,
                'message' => 'Afiliación de empleado actualizada exitosamente',
                'data' => $employeeAffiliation
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Error de validación en EmployeeAffiliationController@update: ' . json_encode($e->errors()));
            return response()->json([
                'status' => 422,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error en EmployeeAffiliationController@update: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Error al actualizar afiliación de empleado: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            Log::info('Eliminando afiliación de empleado ID: ' . $id);
            
            $employeeAffiliation = EmployeeAffiliation::find($id);
            
            if (!$employeeAffiliation) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Afiliación de empleado no encontrada'
                ], 404);
            }

            $employeeAffiliation->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Afiliación removida exitosamente'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en EmployeeAffiliationController@destroy: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Error al remover afiliación: ' . $e->getMessage()
            ], 500);
        }
    }
}