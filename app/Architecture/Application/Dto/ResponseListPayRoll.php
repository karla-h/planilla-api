<?php

namespace App\Architecture\Application\Dto;

use Spatie\LaravelData\Data;

class ResponseListPayRoll extends Data
{
    public string $name;
    public string $dni;
    public string $headquarter;
    public string $pay_date;
    public float $accounting_salary;
    public float $real_salary;
    public $discounts;
    public $additionals;
    public $biweeklyPayments;
}
