<?php

namespace App\Architecture\Domain\Models\UseCases;

interface IBaseUseCase
{
    public function findAll();

    public function create($request);

    public function findBy($key);

    public function edit($key, $request);

    public function delete($key);
}
