<?php

namespace App\Architecture\Infrastructure\Repositories;

use App\Architecture\Domain\Models\Entities\AdditionalPaymentData;
use App\Models\AdditionalPayment;

class AdditionalPaymentRepository implements IBaseRepository
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function create($data) {
        $additionalpayment = AdditionalPayment::create($data);
        return AdditionalPaymentData::from($additionalpayment);
    }

    public function edit($key, $data) {
        $additionalpayment = AdditionalPayment::findOrFail($key);
        return AdditionalPaymentData::from($additionalpayment->update($data));
    }

    public function findBy($key) {
        return AdditionalPaymentData::optional(AdditionalPayment::where('dni', '=', $key)->first());
    }

    public function findAll() {
        return AdditionalPaymentData::collect(AdditionalPayment::where('status_code', '=', 'active')->get());
    }

    public function delete($key) {
        $additionalpayment = AdditionalPayment::findOrFail($key);
        $additionalpayment->status_code = "deleted";
        return AdditionalPaymentData::from($additionalpayment->save());
    }
}
