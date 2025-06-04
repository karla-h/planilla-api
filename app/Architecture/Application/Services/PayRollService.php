<?php

namespace App\Architecture\Application\Services;

use App\Architecture\Domain\Models\UseCases\IPayRollUseCase;
use App\Architecture\Infrastructure\Repositories\PayRollRepository;

class PayRollService
{
    public function __construct(protected PayRollRepository $repository) {}

    public function findAll($request)
    {
        return $this->repository->findAll($request);
    }

    public function create($request)
    {
        return $this->repository->create($request->validated());
    }

    public function findBy($key) {
        return $this->repository->findBy($key);
    }

    public function edit($key, $request) {
        return $this->repository->edit($key, $request);
    }

    public function delete($key) {
        return $this->repository->delete($key);
    }


    public function findByEmployeeAndPaydate($dni, $pay_date) {
        return $this->repository->findByEmployeeAndPaydate($dni, $pay_date);
    }

    public function generatePayRolls($headquarter, $pay_date) {
        return $this->repository->generatePayRolls($headquarter, $pay_date);
    }

    public function createForAllEmployees() {
        return $this->repository->createForAllEmployees();
    }

    public function createPayrollsForSpecificEmployees($request) {
        return $this->repository->createPayrollsForSpecificEmployees($request);
    }
}
