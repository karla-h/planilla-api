<?php

namespace App\Architecture\Infrastructure\Repositories;

use App\Architecture\Domain\Models\Entities\ContractData;
use App\Exceptions\EntityNotFoundException;
use App\Http\Requests\ContractRequest;
use App\Models\Contract;
use Illuminate\Support\Facades\DB;

class ContractRepository
{
    public function create(array | ContractRequest $data)
    {
        DB::beginTransaction();
        try {
            if ($data instanceof ContractRequest) {
                $data = $data->validated();
            }
            
            $contract = Contract::create($data);
            
            DB::commit();
            
            return [
                'message' => 'Contract created successfully',
                'data' => ContractData::from($contract),
                'status' => 201
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            return [
                'message' => 'Error, data cannot be processed',
                'status' => 500
            ];
        }
    }

    public function edit($key, ContractRequest $data)
    {
        DB::beginTransaction();
        try {
            $contract = Contract::find($key);

            if (!$contract) {
                throw new EntityNotFoundException('Contract not found');
            }

            $contract->update($data->validated());
            
            DB::commit();
            
            return [
                'message' => 'Contract updated successfully', 
                'status' => 200
            ];
        } catch (EntityNotFoundException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $th) {
            DB::rollBack();
            return [
                'message' => 'Error updating contract',
                'status' => 500
            ];
        }
    }

    public function findBy($key)
    {
        try {
            $contract = Contract::find($key);

            if (!$contract) {
                throw new EntityNotFoundException('Contract not found');
            }

            return ContractData::optional($contract);
        } catch (EntityNotFoundException $e) {
            throw $e;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function findAll()
    {
        try {
            return ContractData::collect(Contract::all());
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function delete($key)
    {
        DB::beginTransaction();
        try {
            $contract = Contract::find($key);

            if (!$contract) {
                throw new EntityNotFoundException('Contract not found');
            }

            $contract->delete();
            
            DB::commit();
            
            return [
                'message' => 'Contract deleted successfully', 
                'status' => 200
            ];
        } catch (EntityNotFoundException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $th) {
            DB::rollBack();
            return [
                'message' => 'Error deleting contract',
                'status' => 500
            ];
        }
    }
}