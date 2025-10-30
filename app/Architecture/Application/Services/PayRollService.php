<?php

namespace App\Architecture\Application\Services;

use App\Architecture\Domain\Models\UseCases\IPayRollUseCase;
use App\Architecture\Infrastructure\Repositories\PayRollRepository;

class PayRollService implements IPayRollUseCase
{
    public function __construct(
        protected PayRollRepository $repository
    ) {}

    public function findAll($request)
    {
        return $this->repository->findAll($request);
    }

    public function create($request)
    {
        // Cambiar de $request->validated() a solo $request
        return $this->repository->create($request);
    }

    public function findBy($key) 
    {
        return $this->repository->findBy($key);
    }

    public function edit($key, $request) 
    {
        // Aquí también cambiar si es necesario
        return $this->repository->edit($key, $request);
    }

    public function delete($key) 
    {
        return $this->repository->delete($key);
    }

    public function findByEmployeeAndPaydate($dni, $pay_date) 
    {
        return $this->repository->findByEmployeeAndPaydate($dni, $pay_date);
    }

    public function generatePayRolls($headquarter, $pay_date) 
    {
        return $this->repository->generatePayRolls($headquarter, $pay_date);
    }

    public function createForAllEmployees() 
    {
        return $this->repository->createForAllEmployees();
    }

    public function createPayrollsForSpecificEmployees($request) 
    {
        return $this->repository->createPayrollsForSpecificEmployees($request);
    }

    public function calculatePayment($employeeId, $year, $month, $periodType = null)
    {
        return $this->repository->calculatePayment($employeeId, $year, $month, $periodType);
    }

    public function generateProportionalBiweeklyPayments($employeeId, $year, $month, $biweekly)
    {
        return $this->repository->generateProportionalBiweeklyPayments($employeeId, $year, $month, $biweekly);
    }

    public function regenerateBiweeklyPayment($payrollId, $biweeklyId)
    {
        return $this->repository->regenerateBiweeklyPayment($payrollId, $biweeklyId);
    }

    public function deleteBiweeklyPayment($payrollId, $biweeklyId)
    {
        return $this->repository->deleteBiweeklyPayment($payrollId, $biweeklyId);
    }

    public function openPayroll($id)
    {
        return $this->repository->openPayroll($id);
    }

    public function closePayroll($id)
    {
        return $this->repository->closePayroll($id);
    }

    public function lockPayroll($id)
    {
        return $this->repository->lockPayroll($id);
    }

    public function unlockPayroll($id)
    {
        return $this->repository->unlockPayroll($id);
    }
}