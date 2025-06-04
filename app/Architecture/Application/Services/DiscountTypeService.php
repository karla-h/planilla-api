<?php

namespace App\Architecture\Application\Services;

use App\Architecture\Domain\Models\UseCases\IDiscountTypeUseCase;
use App\Architecture\Infrastructure\Repositories\DiscountTypeRepository;

class DiscountTypeService implements IDiscountTypeUseCase
{
    /**
     * Create a new class instance.
     */
    public function __construct(protected DiscountTypeRepository $repository){}

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
