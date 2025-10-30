<?php

namespace App\Architecture\Application\Services;

use App\Architecture\Domain\Models\UseCases\IEmployeeUseCase;
use App\Architecture\Infrastructure\Repositories\EmployeeRepository;

class EmployeeService implements IEmployeeUseCase
{
    public function __construct(protected EmployeeRepository $employeeRepository) {}

    public function create($request) {
        // Cambiar de $request->validated() a $request (array)
        return $this->employeeRepository->create($request);
    }

    public function findBy($key) {
        return $this->employeeRepository->findBy($key);
    }

    public function edit($key, $request) {
        // Cambiar de $request->validated() a $request (array)
        return $this->employeeRepository->edit($key, $request);
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