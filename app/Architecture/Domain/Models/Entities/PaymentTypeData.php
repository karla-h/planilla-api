<?php

namespace App\Architecture\Domain\Models\Entities;

use Spatie\LaravelData\Data;

class PaymentTypeData extends Data
{
    public $id;
    public $description;
    public $value;
}
