<?php

namespace App\Architecture\Infrastructure\Repositories;

use App\Architecture\Domain\Models\Entities\DiscountPaymentData;
use App\Models\DiscountPayment;

class DiscountPaymentRepository implements IBaseRepository
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function create($data) {
        $discountpayment = DiscountPayment::create($data);
        return DiscountPaymentData::from($discountpayment);
    }

    public function edit($key, $data) {
        $discountpayment = DiscountPayment::findOrFail($key);
        return DiscountPaymentData::from($discountpayment->update($data));
    }

    public function findBy($key) {
        return DiscountPaymentData::optional(DiscountPayment::where('dni', '=', $key)->first());
    }

    public function findAll() {
        return DiscountPaymentData::collect(DiscountPayment::where('status_code', '=', 'active')->get());
    }

    public function delete($key) {
        $discountpayment = DiscountPayment::findOrFail($key);
        $discountpayment->status_code = "deleted";
        return DiscountPaymentData::from($discountpayment->save());
    }
}
