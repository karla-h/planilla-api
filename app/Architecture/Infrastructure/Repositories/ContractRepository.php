<?php

namespace App\Architecture\Infrastructure\Repositories;

use App\Architecture\Domain\Models\Entities\ContractData;
use App\Exceptions\EntityNotFoundException;
use App\Http\Requests\ContractRequest;
use App\Models\Contract;

class ContractRepository
{
    public function create(array | ContractRequest $data)
    {
        try {
            if ($data instanceof ContractRequest) {
                $data = $data->validated();
            }
            $contract = Contract::create($data->validated());
            return [
                'message' => 'Contract created successfully',
                'data' => ContractData::from($contract),
                'status' => 201
            ];
        } catch (\Throwable $th) {
            return ['message' => 'Error, data cannot be processed: ' . $th->getMessage(), 'status' => 500];
        }
    }

    public function edit($key, ContractRequest $data)
    {
        $contract = Contract::find($key);

        if (!$contract) {
            throw new EntityNotFoundException('Contract not found');
        }

        $contract->update($data->validated());
        return ['message' => 'Contract updated successfully', 'status' => 200];
    }

    public function findBy($key)
    {
        $contract = Contract::find($key);

        if (!$contract) {
            throw new EntityNotFoundException('Contract not found');
        }

        return ContractData::optional($contract);
    }

    public function findAll()
    {
        return ContractData::collect(Contract::all());
    }

    public function delete($key)
    {
        $contract = Contract::find($key);

        if (!$contract) {
            throw new EntityNotFoundException('Contract not found');
        }

        $contract->delete();
        return ['message' => 'Contract deleted successfully', 'status' => 200];
    }

}