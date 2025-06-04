<?php

namespace App\Architecture\Domain\Models\Entities;

use Spatie\LaravelData\Data;
class DiscountPaymentData extends Data
{
    public $discountType;
    public $amount;
}
