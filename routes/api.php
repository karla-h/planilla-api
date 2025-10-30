<?php

use App\Http\Controllers\AdditionalPaymentController;
use App\Http\Controllers\AffiliationsController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\BiweeklyPaymentController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DebugPayRollController;
use App\Http\Controllers\DebugPayRollServiceController;
use App\Http\Controllers\DiscountPaymentController;
use App\Http\Controllers\DiscountTypeController;
use App\Http\Controllers\EmployeeAffiliationController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExtraController;
use App\Http\Controllers\HeadquarterController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\PaymentTypeController;
use App\Http\Controllers\PayRollController;
use App\Http\Controllers\ReportGeneratorController;
use App\Http\Middleware\IsAdmin;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\FacadesLog;
use Illuminate\Support\Facades\Route;

// Route::post('register', [RegisteredUserController::class,'store']);
//Route::post('login', [AuthenticatedSessionController::class,'store']);
//Route::middleware(['auth:api'])->group(function () {
    Route::post('password', [NewPasswordController::class,'store']);
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::get('profile', [ProfileController::class,'store']);
    
    //Route::middleware([IsAdmin::class])->group(function () {
        Route::apiResources([
            'employees' => EmployeeController::class,
            'payment-types' => PaymentTypeController::class,
            'additional-payments' => AdditionalPaymentController::class,
            'discount-types' => DiscountTypeController::class,
            'discount-payments' => DiscountPaymentController::class,
            'headquarters' => HeadquarterController::class,
            'biweekly-payments' => BiweeklyPaymentController::class,
            'affiliations' => AffiliationsController::class,
            'contracts' => ContractController::class,
            'pay-rolls' => PayRollController::class,
            'extras' => ExtraController::class,
            'campaigns' => CampaignController::class,
            'loans' => LoanController::class,
            'employee-affiliations' => EmployeeAffiliationController::class
        ]);

        Route::get('employees/get/without', [EmployeeController::class, 'getEmployeesWithoutPayroll']);
        Route::get('employees/get/birth/{birth}', [EmployeeController::class, 'getEmployeesByBirthday']);
        Route::get('pay-rolls/find/report', [PayRollController::class, 'findByEmployeeAndPaydate']);
        Route::get('pay-rolls/create/all', [PayRollController::class, 'createForAllEmployees']);
        Route::post('pay-rolls/create/specific', [PayRollController::class, 'createPayrollsForSpecificEmployees']);
        Route::get('report/generate', [ReportGeneratorController::class, 'generatePayRolls']);
        Route::post('biweekly-payments/report', [BiweeklyPaymentController::class, 'reportByBiweekly']);
        Route::post('campaigns/apply', [CampaignController::class,'campaignForPayrolls']);
    //});

    // En routes/api.php, dentro del grupo admin, añade:
Route::post('contracts/{id}/terminate', [ContractController::class, 'terminate']);
Route::post('contracts/{id}/suspend', [ContractController::class, 'suspend']);
Route::post('contracts/{id}/activate', [ContractController::class, 'activate']);
Route::get('contracts/employee/{employeeId}/active', [ContractController::class, 'getActiveByEmployee']);
Route::get('contracts/employee/{employeeId}', [ContractController::class, 'getByEmployee']);

Route::get('employee-affiliations', [EmployeeAffiliationController::class, 'index']);
    Route::post('employee-affiliations', [EmployeeAffiliationController::class, 'store']);
    Route::get('employee-affiliations/{id}', [EmployeeAffiliationController::class, 'show']);
    Route::put('employee-affiliations/{id}', [EmployeeAffiliationController::class, 'update']);
    Route::delete('employee-affiliations/{id}', [EmployeeAffiliationController::class, 'destroy']);
    Route::get('employee-affiliations/employee/{employeeId}', [EmployeeAffiliationController::class, 'getByEmployee']);

    Route::post('pay-rolls/employee/{employeeId}/generate-biweekly', [PayRollController::class, 'generateProportionalBiweeklyPayment']);
//});// Gestión de estados
// En routes/api.php
Route::post('pay-rolls/{id}/open', [PayRollController::class, 'openPayroll']);
Route::post('pay-rolls/{id}/close', [PayRollController::class, 'closePayroll']);
Route::post('pay-rolls/{id}/lock', [PayRollController::class, 'lockPayroll']);
Route::post('pay-rolls/{id}/unlock', [PayRollController::class, 'unlockPayroll']);
Route::post('pay-rolls/{payrollId}/regenerate-payment', [PayRollController::class, 'regenerateBiweeklyPayment']);
Route::delete('pay-rolls/{payrollId}/payments/{biweeklyId}', [PayRollController::class, 'deleteBiweeklyPayment']);
Route::get('pay-rolls/{id}/permissions', [PayRollController::class, 'getPayrollPermissions']);
Route::get('pay-rolls/{id}/status', [PayRollController::class, 'getPayrollStatus']);
Route::post('biweekly-payments/create-all', [BiweeklyPaymentController::class, 'createForAllEmployees']);


// ... tus rutas existentes ...

// ==================== RUTAS DE CÁLCULO Y DEBUG ====================

// Cálculo de pagos
Route::post('pay-rolls/employee/{employeeId}/calculate-payment', [PayRollController::class, 'calculatePayment']);
Route::post('calculate-payment-by-dni', function (\Illuminate\Http\Request $request) {
    try {
        $dni = $request->input('dni');
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);
        $periodType = $request->input('period_type');
        
        Log::info('Calculate payment by DNI', [
            'dni' => $dni,
            'year' => $year,
            'month' => $month,
            'period_type' => $periodType
        ]);
        
        $employee = \App\Models\Employee::where('dni', $dni)->first();
        
        if (!$employee) {
            return response()->json([
                'message' => 'Empleado no encontrado',
                'status' => 404
            ], 404);
        }
        
        // Usar el service existente
        $payrollService = app()->make('App\Architecture\Application\Services\PayRollService');
        $result = $payrollService->calculatePayment($employee->id, $year, $month, $periodType);
        
        return response()->json($result, $result['status']);
        
    } catch (\Exception $e) {
        Log::error('Error en calculate-payment-by-dni', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'message' => 'Error: ' . $e->getMessage(),
            'status' => 500
        ], 500);
    }
});

// Debug endpoints
Route::get('debug-employees-with-payrolls', function () {
    $currentYear = now()->year;
    $currentMonth = now()->month;
    
    $employeesWithPayrolls = \App\Models\PayRoll::with(['employee', 'employee.activeContract'])
        ->whereYear('created_at', $currentYear)
        ->whereMonth('created_at', $currentMonth)
        ->get()
        ->map(function ($payroll) {
            return [
                'employee_id' => $payroll->employee->id,
                'dni' => $payroll->employee->dni,
                'name' => $payroll->employee->firstname . ' ' . $payroll->employee->lastname,
                'payroll_id' => $payroll->id,
                'has_active_contract' => $payroll->employee->activeContract ? 'YES' : 'NO',
                'contract_type' => $payroll->employee->activeContract ? $payroll->employee->activeContract->payment_type : 'N/A'
            ];
        });
    
    return response()->json([
        'employees_with_payrolls' => $employeesWithPayrolls,
        'current_period' => "{$currentYear}-{$currentMonth}"
    ]);
});

Route::get('debug-all-employees', function () {
    $employees = \App\Models\Employee::with(['activeContract', 'payrolls' => function($q) {
        $q->whereYear('created_at', now()->year)
          ->whereMonth('created_at', now()->month);
    }])->get()->map(function($emp) {
        return [
            'id' => $emp->id,
            'dni' => $emp->dni, 
            'name' => $emp->firstname . ' ' . $emp->lastname,
            'has_active_contract' => $emp->activeContract ? 'YES' : 'NO',
            'contract_type' => $emp->activeContract ? $emp->activeContract->payment_type : 'N/A',
            'has_payroll_this_month' => $emp->payrolls->isNotEmpty() ? 'YES' : 'NO',
            'payroll_id' => $emp->payrolls->first()?->id
        ];
    });
    
    return response()->json($employees);
});

// Test endpoints
Route::post('test-employee-service', function (\Illuminate\Http\Request $request) {
    Log::info('=== TEST EMPLOYEE SERVICE - INICIANDO ===', $request->all());
    
    try {
        $validated = $request->validate([
            'firstname' => 'required',
            'lastname' => 'required', 
            'dni' => 'required|unique:employees,dni',
            'born_date' => 'required|date',
            'headquarter_id' => 'required|exists:headquarters,id'
        ]);

        Log::info('Datos validados', $validated);
        
        // Usar el Service real
        $employeeService = app()->make('App\Architecture\Application\Services\EmployeeService');
        Log::info('Service instanciado');
        
        $result = $employeeService->create($validated);
        Log::info('Service respondió', $result);
        
        return response()->json($result, $result['status']);
        
    } catch (\Exception $e) {
        Log::error('=== ERROR EN TEST EMPLOYEE SERVICE ===', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'message' => 'Error en test service: ' . $e->getMessage(),
            'status' => 500
        ]);
    }
});