<?php

namespace App\Architecture\Infrastructure\Repositories;

interface IBaseRepository
{
    public function findAll();

    public function create($data);

    public function findBy($key);

    public function edit($key, $data);

    public function delete($key);
}
