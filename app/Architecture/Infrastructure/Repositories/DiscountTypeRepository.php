<?php

namespace App\Architecture\Infrastructure\Repositories;

use App\Architecture\Domain\Models\Entities\DiscountTypeData;
use App\Exceptions\EntityNotFoundException;
use App\Models\DiscountType;

class DiscountTypeRepository implements IBaseRepository
{
    public function create($data)
    {
        try {
            $discountType = DiscountType::create($data);
            return [
                'message' => 'Payment type created successfully',
                'data' => DiscountTypeData::from($discountType),
                'status' => 201
            ];
        } catch (\Throwable $th) {
            return ['message' => 'Error, data cannot be processed: ' . $th->getMessage(), 'status' => 500];
        }
    }

    public function edit($key, $data)
    {
        $discountType = DiscountType::find($key);

        if (!$discountType) {
            throw new EntityNotFoundException('Payment type not found');
        }

        $discountType->update($data);
        return ['message' => 'Payment type updated successfully', 'status' => 200];
    }

    public function findBy($key)
    {
        $discountType = DiscountType::find($key);

        if (!$discountType) {
            throw new EntityNotFoundException('Payment type not found');
        }

        return DiscountTypeData::optional($discountType);
    }

    public function findAll()
    {
        return DiscountTypeData::collect(DiscountType::all());
    }

    public function delete($key)
    {
        $discountType = DiscountType::find($key);

        if (!$discountType) {
            throw new EntityNotFoundException('Payment type not found');
        }

        $discountType->delete();
        return ['message' => 'Payment type deleted successfully', 'status' => 202];
    }
}
