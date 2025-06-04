<?php

namespace App\Architecture\Application\Services;

use App\Architecture\Domain\Models\UseCases\IAffiliationUseCase;
use App\Architecture\Infrastructure\Repositories\AffiliationRepository;

class AffiliationService implements IAffiliationUseCase
{
    public function __construct(protected AffiliationRepository $repository) {}

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
