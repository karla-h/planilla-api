<?php

namespace App\Architecture\Application\Services;

use App\Architecture\Domain\Models\UseCases\IBiweeklyUseCase;
use App\Architecture\Infrastructure\Repositories\BiweeklyPaymentRepository;

class BiweeklyPaymentService
{
    public function __construct(protected BiweeklyPaymentRepository $biweeklyPaymentRepository) {}

    public function create($request) {
        return $this->biweeklyPaymentRepository->create($request);
    }

    public function createForAllEmployees() {
        return $this->biweeklyPaymentRepository->createForAllEmployees();
    }

    public function reportByBiweekly($request) {
        return $this->biweeklyPaymentRepository->reportByBiweekly($request);
    }
}