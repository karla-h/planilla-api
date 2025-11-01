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
Route::post('password', [NewPasswordController::class, 'store']);
Route::post('logout', [AuthenticatedSessionController::class, 'destroy']);
Route::get('profile', [ProfileController::class, 'store']);

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
Route::post('campaigns/apply', [CampaignController::class, 'campaignForPayrolls']);
//});

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

Route::post('pay-rolls/{id}/open', [PayRollController::class, 'openPayroll']);
Route::post('pay-rolls/{id}/close', [PayRollController::class, 'closePayroll']);
Route::post('pay-rolls/{payrollId}/regenerate-payment', [PayRollController::class, 'regenerateBiweeklyPayment']);
Route::delete('pay-rolls/{payrollId}/payments/{biweeklyId}', [PayRollController::class, 'deleteBiweeklyPayment']);
Route::get('pay-rolls/{id}/permissions', [PayRollController::class, 'getPayrollPermissions']);
Route::get('pay-rolls/{id}/status', [PayRollController::class, 'getPayrollStatus']);
Route::post('biweekly-payments/create-all', [BiweeklyPaymentController::class, 'createForAllEmployees']);

// Cálculo de pagos
Route::post('pay-rolls/employee/{employeeId}/calculate-payment', [PayRollController::class, 'calculatePayment']);
Route::post('pay-rolls/employee/{employeeId}/generate-payment', [PayRollController::class, 'generatePayment']);

//listaar
Route::post('employee/payrolls/{dni}', [PayRollController::class, 'getEmployeePayrolls']);
Route::post('employee/biweeks/{dni}', [PayRollController::class, 'getEmployeeBiweeks']);
Route::post('payrolls/biweeks/{payrollId}', [PayRollController::class, 'getPayrollBiweeks']);
