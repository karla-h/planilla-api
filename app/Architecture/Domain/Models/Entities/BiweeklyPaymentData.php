<?php

namespace App\Architecture\Domain\Models\Entities;

use Spatie\LaravelData\Data;

class BiweeklyPaymentData extends Data
{
        public $biweekly;
        public $biweekly_date;
        public $accounting_amount;
        public $real_amount;
        public $discounts;
        public $additionals;

}
