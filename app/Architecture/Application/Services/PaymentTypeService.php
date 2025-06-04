<?php

namespace App\Architecture\Application\Services;

use App\Architecture\Domain\Models\UseCases\IPaymentTypeUseCase;
use App\Architecture\Infrastructure\Repositories\PaymentTypeRepository;

class PaymentTypeService implements IPaymentTypeUseCase
{
    public function __construct(protected PaymentTypeRepository $repository) {}

    public function findAll()
    {
        return $this->repository->findAll();
    }

    public function create($request)
    {
        return $this->repository->create($request->validated());
    }

    public function findBy($key)
    {
        return $this->repository->findBy($key);
    }

    public function edit($key, $request)
    {
        return $this->repository->edit($key, $request->validated());
    }

    public function delete($key)
    {
        return $this->repository->delete($key);
    }
}
