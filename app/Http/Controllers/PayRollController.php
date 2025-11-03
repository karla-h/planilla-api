<?php

namespace App\Http\Controllers;

use App\Architecture\Application\Services\PayRollService;
use App\Http\Requests\PayRollRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayRollController extends Controller
{
    public function __construct(protected PayRollService $service) {}

    public function index(Request $request): JsonResponse
    {
        try {
            Log::info('PayRollController@index - Obteniendo planillas');
            $year = $request->filled('year') ? $request->input('year') : now()->year;
            $month = $request->filled('month') ? $request->input('month') : now()->month;
            $requestData = ['year' => $year, 'month' => $month];

            $result = $this->service->findAll($requestData);
            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Error en PayRollController@index', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function store(PayRollRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $payroll = $this->service->create($validatedData);

            return response()->json($payroll, $payroll['status']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear planilla: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(PayRollRequest $request, $id): JsonResponse
    {
        try {
            Log::info('PayRollController@update - Actualizando planilla', ['id' => $id]);

            // Pasar los datos validados al service
            $payroll = $this->service->edit($id, $request->validated());

            return response()->json($payroll, $payroll['status']);
        } catch (\Exception $e) {
            Log::error('Error en PayRollController@update', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return response()->json([
                'message' => 'Error al actualizar planilla'
            ], 500);
        }
    }

    // ✅ CORREGIDO: Este método ahora busca por ID de planilla, no por DNI
    public function show($id): JsonResponse
    {
        try {
            Log::info('PayRollController@show - Buscando planilla por ID', ['id' => $id]);
            
            // Verificar si es numérico (ID) o string (DNI) para compatibilidad
            if (is_numeric($id)) {
                // Buscar por ID de planilla
                $request = $this->service->findById($id);
            } else {
                // Buscar por DNI (mantener compatibilidad)
                $request = $this->service->findBy($id);
            }
            
            return response()->json($request, $request['status']);
        } catch (\Exception $e) {
            Log::error('Error en PayRollController@show', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return response()->json([
                'message' => 'Error al obtener planilla'
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        return response()->json(['message' => 'Método no implementado'], 501);
    }

    public function createForAllEmployees(): JsonResponse
    {
        try {
            Log::info('PayRollController@createForAllEmployees - Creando planillas masivas');
            $response = $this->service->createForAllEmployees();
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            Log::error('Error en PayRollController@createForAllEmployees', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Error al crear planillas masivas'
            ], 500);
        }
    }

    public function findByEmployeeAndPaydate(Request $request): JsonResponse
    {
        try {
            Log::info('PayRollController@findByEmployeeAndPaydate - Buscando por empleado y fecha', $request->all());
            $response = $this->service->findByEmployeeAndPaydate($request['dni'], $request['pay_date']);
            return response()->json($response, 200);
        } catch (\Exception $e) {
            Log::error('Error en PayRollController@findByEmployeeAndPaydate', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Error en la búsqueda'
            ], 500);
        }
    }

    public function createPayrollsForSpecificEmployees(Request $request): JsonResponse
    {
        try {
            Log::info('PayRollController@createPayrollsForSpecificEmployees - Creando planillas específicas', $request->all());
            $response = $this->service->createPayrollsForSpecificEmployees($request);
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            Log::error('Error en PayRollController@createPayrollsForSpecificEmployees', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Error al crear planillas específicas'
            ], 500);
        }
    }

    public function calculatePayment(Request $request, $employeeId): JsonResponse
    {
        try {
            Log::info('PayRollController@calculatePayment - Calculando pago', [
                'employeeId' => $employeeId,
                'request' => $request->all()
            ]);
            $year = $request->input('year', now()->year);
            $month = $request->input('month', now()->month);
            $periodType = $request->input('period_type');

            $response = $this->service->calculatePayment($employeeId, $year, $month, $periodType);
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            Log::error('Error en PayRollController@calculatePayment', [
                'error' => $e->getMessage(),
                'employeeId' => $employeeId
            ]);
            return response()->json([
                'message' => 'Error en el cálculo de pago'
            ], 500);
        }
    }

    // Métodos de gestión de estados
    public function openPayroll($id): JsonResponse
    {
        try {
            Log::info('PayRollController@openPayroll - Abriendo planilla', ['id' => $id]);
            $response = $this->service->openPayroll($id);
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            Log::error('Error en PayRollController@openPayroll', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return response()->json(['message' => 'Error al abrir planilla'], 500);
        }
    }

    public function closePayroll($id): JsonResponse
    {
        try {
            Log::info('PayRollController@closePayroll - Cerrando planilla', ['id' => $id]);
            $response = $this->service->closePayroll($id);
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            Log::error('Error en PayRollController@closePayroll', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return response()->json(['message' => 'Error al cerrar planilla'], 500);
        }
    }

    public function getPayrollPermissions($id): JsonResponse
    {
        try {
            Log::info('PayRollController@getPayrollPermissions - Obteniendo permisos', ['id' => $id]);
            $payroll = \App\Models\PayRoll::findOrFail($id);
            return response()->json([
                'status' => 200,
                'data' => [
                    'current_status' => $payroll->status,
                    'permissions' => $payroll->getAllPermissions(),
                    'payment_type' => $payroll->getPaymentType(),
                    'biweekly_payments' => $payroll->biweeklyPayments,
                    'can_regenerate' => $payroll->canEditPayments()
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error en PayRollController@getPayrollPermissions', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return response()->json(['message' => 'Error al obtener permisos'], 500);
        }
    }

    public function getPayrollStatus($id): JsonResponse
    {
        try {
            Log::info('PayRollController@getPayrollStatus - Obteniendo estado', ['id' => $id]);
            $payroll = \App\Models\PayRoll::findOrFail($id);
            return response()->json([
                'status' => 200,
                'data' => [
                    'current_status' => $payroll->status,
                    'payment_type' => $payroll->getPaymentType(),
                    'expected_payments' => $payroll->getExpectedBiweeklyPayments(),
                    'completed_payments' => $payroll->getCompletedBiweeklyPayments(),
                    'can_edit' => $payroll->canEdit(),
                    'period' => [
                        'start' => $payroll->period_start,
                        'end' => $payroll->period_end
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error en PayRollController@getPayrollStatus', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return response()->json(['message' => 'Error al obtener estado'], 500);
        }
    }

    public function regenerateBiweeklyPayment(Request $request, $payrollId): JsonResponse
    {
        try {
            Log::info('PayRollController@regenerateBiweeklyPayment - Regenerando pago', [
                'payrollId' => $payrollId,
                'request' => $request->all()
            ]);
            $biweeklyId = $request->input('biweekly_payment_id');
            $response = $this->service->regenerateBiweeklyPayment($payrollId, $biweeklyId);
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            Log::error('Error en PayRollController@regenerateBiweeklyPayment', [
                'error' => $e->getMessage(),
                'payrollId' => $payrollId
            ]);
            return response()->json(['message' => 'Error al regenerar pago'], 500);
        }
    }

    public function deleteBiweeklyPayment($payrollId, $biweeklyId): JsonResponse
    {
        try {
            Log::info('PayRollController@deleteBiweeklyPayment - Eliminando pago', [
                'payrollId' => $payrollId,
                'biweeklyId' => $biweeklyId
            ]);
            $response = $this->service->deleteBiweeklyPayment($payrollId, $biweeklyId);
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            Log::error('Error en PayRollController@deleteBiweeklyPayment', [
                'error' => $e->getMessage(),
                'payrollId' => $payrollId
            ]);
            return response()->json(['message' => 'Error al eliminar pago'], 500);
        }
    }

    public function generatePayment(Request $request, $employeeId): JsonResponse
    {
        // Este método RECIBE la request y llama al service
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);
        $biweekly = $request->input('biweekly', null);

        $response = $this->service->generatePayments($employeeId, $year, $month, $biweekly);
        return response()->json($response, $response['status']);
    }

    public function getEmployeePayrolls(Request $request, $dni): JsonResponse
    {
        try {
            $filters = [
                'year' => $request->input('year'),
                'month' => $request->input('month')
            ];

            $response = $this->service->getEmployeePayrolls($dni, $filters);
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al obtener planillas del empleado: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getEmployeeBiweeks(Request $request, $dni): JsonResponse
    {
        try {
            $filters = [
                'year' => $request->input('year'),
                'month' => $request->input('month')
            ];

            $response = $this->service->getEmployeeBiweeks($dni, $filters);
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al obtener quincenas del empleado: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getPayrollBiweeks(Request $request, $payrollId): JsonResponse
    {
        try {
            $filters = [
                'year' => $request->input('year'),
                'month' => $request->input('month')
            ];

            $response = $this->service->getPayrollBiweeks($payrollId, $filters);
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al obtener quincenas de la planilla: ' . $e->getMessage()
            ], 500);
        }
    }
}