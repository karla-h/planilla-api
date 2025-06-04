<?php

namespace App\Architecture\Application\Services;

use App\Architecture\Domain\Models\UseCases\IHeadquarterUseCase;
use App\Architecture\Infrastructure\Repositories\HeadquarterRepository;
use App\Http\Requests\HeadquarterRequest;

class HeadquarterService implements IHeadquarterUseCase
{
    public function __construct(protected HeadquarterRepository $headquarterRepository) {}

    public function findAll()
    {
        return $this->headquarterRepository->findAll();
    }

    public function create($request)
    {
        return $this->headquarterRepository->create($request->validated());
    }

    public function findBy($key)
    {
        return $this->headquarterRepository->findBy($key);
    }

    public function edit($key, $request)
    {
        return $this->headquarterRepository->edit($key, $request->validated());
    }

    public function delete($key)
    {
        return $this->headquarterRepository->delete($key);
    }
}
