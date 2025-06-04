<?php

namespace App\Architecture\Domain\Models\Entities;

use Spatie\LaravelData\Data;

class ContractData extends Data
{
    public $hire_date;
    public $accounting_salary;
    public $real_salary;
    public $termination_date;
    public $termination_reason;
}