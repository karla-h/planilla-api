<?php

namespace App\Architecture\Domain\Models\Entities;

use Spatie\LaravelData\Data;

class DiscountTypeData extends Data
{
    public $id;
    public $description;
    public $value;
}
