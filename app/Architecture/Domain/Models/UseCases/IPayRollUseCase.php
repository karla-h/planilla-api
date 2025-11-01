<?php

namespace App\Architecture\Domain\Models\UseCases;

interface IPayRollUseCase
{
    public function findAll($request);
    public function create($request);
    public function findBy($key);
    public function edit($key, $request);
    public function delete($key);
    public function findByEmployeeAndPaydate($dni, $pay_date);
    public function generatePayRolls($headquarter, $pay_date);
    public function createForAllEmployees();
    public function createPayrollsForSpecificEmployees($request);
    public function calculatePayment($employeeId, $year, $month, $periodType = null);
    public function regenerateBiweeklyPayment($payrollId, $biweeklyId);
    public function deleteBiweeklyPayment($payrollId, $biweeklyId);
    public function openPayroll($id);
    public function closePayroll($id);
}