<?php

namespace App\Architecture\Application\Services;

use App\Architecture\Domain\Models\UseCases\IEmployeeUseCase;
use App\Architecture\Infrastructure\Repositories\EmployeeRepository;

class EmployeeService implements IEmployeeUseCase
{
    /**
     * Create a new class instance.
     */
    public function __construct(protected EmployeeRepository $employeeRepository)
    {
        //
    }

    public function create($request) {
        return $this->employeeRepository->create($request->validated());
    }

    public function findBy($key) {
        return $this->employeeRepository->findBy($key);
    }

    public function edit($key, $request) {
        return $this->employeeRepository->edit($key, $request->validated());
    }

    public function findAll() {
        return $this->employeeRepository->findAll();
    }

    public function delete($key) {
        return $this->employeeRepository->delete($key);
    }

    public function getEmployeesWithoutPayroll() {
        return $this->employeeRepository->getEmployeesWithoutPayroll();
    }

    public function getEmployeesByBirthday($request) {
        return $this->employeeRepository->getEmployeesByBirthday($request);
    }
}
