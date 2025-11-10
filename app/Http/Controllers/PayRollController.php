<?php

namespace App\Http\Controllers;

use App\Architecture\Application\Services\PayrollCalculatorService;
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

    public function show($id): JsonResponse
    {
        try {
            Log::info('PayRollController@show - Buscando planilla por ID', ['id' => $id]);

            if (is_numeric($id)) {
                $request = $this->service->findById($id);
            } else {
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

    public function generateMassPayrolls(Request $request): JsonResponse
    {
        try {
            Log::info('PayRollController@generateMassPayrolls - Generación masiva', $request->all());

            $filters = [
                'headquarter_id' => $request->input('headquarter_id'),
                'payment_type' => $request->input('payment_type'),
                'employee_ids' => $request->input('employee_ids', []),
                'dni_list' => $request->input('dni_list', []),
                'month' => $request->input('month', now()->month),
                'year' => $request->input('year', now()->year)
            ];

            $response = $this->service->generateMassPayrolls($filters);
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            Log::error('Error en PayRollController@generateMassPayrolls', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Error en generación masiva de planillas'
            ], 500);
        }
    }

 public function generateMassBiweeklyPayments(Request $request)
    {
        try {
            Log::info("🎯 Generando pagos masivos desde PayRollController", $request->all());

            $validated = $request->validate([
                'biweekly' => 'required|integer|in:1,2',
                'year' => 'required|integer|min:2020|max:2030',
                'month' => 'required|integer|min:1|max:12',
                'headquarter_id' => 'nullable|integer|exists:headquarters,id',
                'payroll_ids' => 'nullable|array',
                'payroll_ids.*' => 'integer|exists:pay_rolls,id',
                'force_regenerate' => 'boolean',
                'payment_type' => 'nullable|in:quincenal,mensual'
            ]);

            $result = $this->service->generateMassBiweeklyPayments($validated);

            Log::info("✅ Resultado desde service:", [
                'status' => $result['status'] ?? 'unknown',
                'message' => $result['message'] ?? 'No message'
            ]);

            return response()->json($result, $result['status'] ?? 500);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error("❌ Validación fallida:", $e->errors());
            return response()->json([
                'status' => 400,
                'message' => 'Datos de entrada inválidos',
                'errors' => $e->errors()
            ], 400);
            
        } catch (\Exception $e) {
            Log::error('❌ Error en generateMassBiweeklyPayments controller: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'status' => 500,
                'message' => 'Error interno del servidor: ' . $e->getMessage(),
                'data' => [
                    'success' => [],
                    'errors' => [$e->getMessage()],
                    'summary' => [
                        'total_processed' => 0,
                        'successful' => 0,
                        'failed' => 1,
                        'by_payment_type' => [
                            'quincenal' => 0,
                            'mensual' => 0
                        ]
                    ]
                ]
            ], 500);
        }
    }

    public function getPayrollsStatusByMonth(Request $request): JsonResponse
    {
        try {
            Log::info('PayRollController@getPayrollsStatusByMonth - Obteniendo estado de planillas', $request->all());

            $year = $request->input('year', now()->year);
            $month = $request->input('month', now()->month);

            $response = $this->service->getPayrollsStatusByMonth($year, $month);
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            Log::error('Error en PayRollController@getPayrollsStatusByMonth', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Error obteniendo estado de planillas'
            ], 500);
        }
    }

    public function canEditPayroll($id): JsonResponse
    {
        $response = $this->service->checkEditPermissions($id);
        return response()->json($response, $response['status'] ?? 200);
    }

    public function canEditBiweekly($id, $biweekly): JsonResponse
    {
        $response = $this->service->checkEditPermissions($id, $biweekly);
        return response()->json($response, $response['status'] ?? 200);
    }

    public function createAdvance(Request $request, $id): JsonResponse
    {
        $response = $this->service->createAdvance($id, $request->all());
        return response()->json($response, $response['status']);
    }

    public function getMaxAdvance($id, $biweekly): JsonResponse
    {
        $response = $this->service->getMaxAdvance($id, $biweekly);
        return response()->json($response, $response['status']);
    }

    public function getMaxAdvanceByMethod(Request $request, $id, $biweekly): JsonResponse
    {
        $payCard = $request->input('pay_card');
        $response = $this->service->getMaxAdvance($id, $biweekly, $payCard);
        return response()->json($response, $response['status']);
    }

    public function addPayment(Request $request, $id, $type, $biweekly): JsonResponse
    {
        $response = $this->service->addPaymentToPayroll($id, $type, $request->all(), $biweekly);
        return response()->json($response, $response['status']);
    }

    public function editPayment(Request $request, $id, $type, $paymentId, $biweekly): JsonResponse
    {
        $response = $this->service->editPayment($id, $type, $paymentId, $request->all(), $biweekly);
        return response()->json($response, $response['status']);
    }

    public function deletePayment($id, $type, $paymentId, $biweekly): JsonResponse
    {
        $response = $this->service->deletePayment($id, $type, $paymentId, $biweekly);
        return response()->json($response, $response['status']);
    }

    public function reopenPayroll($id): JsonResponse
    {
        $response = $this->service->reopenPayroll($id);
        return response()->json($response, $response['status']);
    }

    public function previewPayment(Request $request, $employeeId)
    {
        try {
            $request->validate([
                'year' => 'required|integer',
                'month' => 'required|integer|between:1,12',
                'biweekly' => 'sometimes|integer|in:1,2'
            ]);

            // ✅ Llamada directa al servicio inyectado
            $result = $this->service->previewPayment(
                $employeeId,
                $request->year,
                $request->month,
                $request->biweekly,
                $request->additional_payments ?? [],
                $request->discount_payments ?? []
            );

            return response()->json($result, $result['status']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
