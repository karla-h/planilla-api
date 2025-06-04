<?php

namespace App\Architecture\Domain\Models\Entities;

use Spatie\LaravelData\Data;

class AdditionalPaymentData extends Data
{
    public $paymentType;
    public $amount;
}
