<?php

use App\Http\Controllers\AdditionalPaymentController;
use App\Http\Controllers\AffiliationsController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\BiweeklyPaymentController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContractController;
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
use Illuminate\Support\Facades\Route;

// ========== AUTH PÚBLICAS ==========
Route::post('login', [AuthenticatedSessionController::class, 'store']);

// ========== AUTH PROTEGIDAS ==========
Route::middleware(['auth:api'])->group(function () {
    Route::post('password', [NewPasswordController::class, 'store']);
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::get('profile', [ProfileController::class, 'store']);

    // ========== ADMIN ROUTES ==========
    Route::middleware([IsAdmin::class])->group(function () {
        
        // ========== CRUD COMPLETOS ==========
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

        // ========== EMPLEADOS ==========
        Route::get('employees/get/without', [EmployeeController::class, 'getEmployeesWithoutPayroll']);
        Route::get('employees/get/birth/{birth}', [EmployeeController::class, 'getEmployeesByBirthday']);

        // ========== CONTRATOS ==========
        Route::post('contracts/{id}/terminate', [ContractController::class, 'terminate']);
        Route::post('contracts/{id}/suspend', [ContractController::class, 'suspend']);
        Route::post('contracts/{id}/activate', [ContractController::class, 'activate']);
        Route::get('contracts/employee/{employeeId}/active', [ContractController::class, 'getActiveByEmployee']);
        Route::get('contracts/employee/{employeeId}', [ContractController::class, 'getByEmployee']);
        
        // ✅ NUEVAS: Suspensiones por periodos
        Route::post('contracts/{id}/suspend-with-periods', [ContractController::class, 'suspendWithPeriods']);
        Route::post('contracts/{id}/reactivate', [ContractController::class, 'reactivate']);

        // ========== AFILIACIONES ==========
        Route::get('employee-affiliations/employee/{employeeId}', [EmployeeAffiliationController::class, 'getByEmployee']);

        // ========== PLANILLAS - CREACIÓN ==========
        Route::get('pay-rolls/create/all', [PayRollController::class, 'createForAllEmployees']);
        Route::post('pay-rolls/create/specific', [PayRollController::class, 'createPayrollsForSpecificEmployees']);

        // ========== PLANILLAS - CONSULTA Y REPORTES ==========
        Route::get('pay-rolls/find/report', [PayRollController::class, 'findByEmployeeAndPaydate']);
        Route::get('pay-rolls/status/month', [PayRollController::class, 'getPayrollsStatusByMonth']);
        Route::post('employee/payrolls/{dni}', [PayRollController::class, 'getEmployeePayrolls']);
        Route::post('employee/biweeks/{dni}', [PayRollController::class, 'getEmployeeBiweeks']);
        Route::post('pay-rolls/biweeks/{payrollId}', [PayRollController::class, 'getPayrollBiweeks']);   

        // ========== PLANILLAS - CONTROL DE EDICIÓN ==========
        Route::get('pay-rolls/{id}/can-edit', [PayRollController::class, 'canEditPayroll']);
        Route::get('pay-rolls/{id}/can-edit-biweekly/{biweekly}', [PayRollController::class, 'canEditBiweekly']);
        
        // ========== PLANILLAS - ESTADOS ==========
        Route::post('pay-rolls/{id}/reopen', [PayRollController::class, 'reopenPayroll']);
        Route::post('pay-rolls/{id}/close', [PayRollController::class, 'closePayroll']);

        // ========== PLANILLAS - GESTIÓN DE PAGOS ==========
        Route::post('pay-rolls/{id}/add-payment/{type}/{biweekly}', [PayRollController::class, 'addPayment']);
        Route::put('pay-rolls/{id}/edit-payment/{type}/{paymentId}/{biweekly}', [PayRollController::class, 'editPayment']);
        Route::delete('pay-rolls/{id}/delete-payment/{type}/{paymentId}/{biweekly}', [PayRollController::class, 'deletePayment']);
        
        // ========== PLANILLAS - ADELANTOS ==========
        Route::post('pay-rolls/{id}/advances', [PayRollController::class, 'createAdvance']);
        Route::get('pay-rolls/{id}/max-advance/{biweekly}', [PayRollController::class, 'getMaxAdvance']);
        Route::get('pay-rolls/{id}/max-advance/{biweekly}/{payCard}', [PayRollController::class, 'getMaxAdvanceByMethod']);

        // ========== PLANILLAS - CÁLCULO Y GENERACIÓN ==========
        Route::post('pay-rolls/employee/{employeeId}/calculate-payment', [PayRollController::class, 'calculatePayment']);
        Route::post('pay-rolls/employee/{employeeId}/generate-payment', [PayRollController::class, 'generatePayment']);
        Route::post('pay-rolls/employee/{employeeId}/preview-payment', [PayRollController::class, 'previewPayment']);
        
        // ========== PLANILLAS - REGENERACIÓN ==========
        Route::post('pay-rolls/{payrollId}/regenerate-payment', [PayRollController::class, 'regenerateBiweeklyPayment']);
        Route::delete('pay-rolls/{payrollId}/payments/{biweeklyId}', [PayRollController::class, 'deleteBiweeklyPayment']);

        // ========== PLANILLAS - OPERACIONES MASIVAS ==========
        Route::post('pay-rolls/generate-mass', [PayRollController::class, 'generateMassPayrolls']);
        Route::post('pay-rolls/generate-mass-payments', [PayRollController::class, 'generateMassBiweeklyPayments']);
        //OLDEST
        Route::post('biweekly-payments/create-all', [BiweeklyPaymentController::class, 'createForAllEmployees']);

        // ========== REPORTES ==========
        Route::get('report/generate', [ReportGeneratorController::class, 'generatePayRolls']);
        Route::post('biweekly-payments/report', [BiweeklyPaymentController::class, 'reportByBiweekly']);

        // ========== CAMPAÑAS ==========
        Route::post('campaigns/apply', [CampaignController::class, 'campaignForPayrolls']);
    });
});