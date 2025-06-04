<?php

namespace App\Architecture\Infrastructure\Repositories;

use App\Architecture\Domain\Models\Entities\PaymentTypeData;
use App\Exceptions\EntityNotFoundException;
use App\Models\PaymentType;

class PaymentTypeRepository implements IBaseRepository
{
    public function create($data)
    {
        try {
            $paymentType = PaymentType::create($data);
            return [
                'message' => 'Payment type created successfully',
                'data' => PaymentTypeData::from($paymentType),
                'status' => 201
            ];
        } catch (\Throwable $th) {
            return ['message' => 'Error, data cannot be processed: ' . $th->getMessage(), 'status' => 500];
        }
    }

    public function edit($key, $data)
    {
        $paymentType = PaymentType::find($key);

        if (!$paymentType) {
            throw new EntityNotFoundException('Payment type not found');
        }

        $paymentType->update($data);
        return ['message' => 'Payment type updated successfully', 'status' => 200];
    }

    public function findBy($key)
    {
        $paymentType = PaymentType::find($key);

        if (!$paymentType) {
            throw new EntityNotFoundException('Payment type not found');
        }

        return PaymentTypeData::optional($paymentType);
    }

    public function findAll()
    {
        return PaymentTypeData::collect(PaymentType::all());
    }

    public function delete($key)
    {
        $paymentType = PaymentType::find($key);

        if (!$paymentType) {
            throw new EntityNotFoundException('Payment type not found');
        }

        $paymentType->delete();
        return ['message' => 'Payment type deleted successfully', 'status' => 202];
    }
}
