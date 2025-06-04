<?php

namespace App\Architecture\Domain\Models\Entities;

use Spatie\LaravelData\Data;

class PayRollData extends Data
{
        public EmployeeData | string $employee;
        public $pay_date;
        public $accounting_salary;
        public $real_salary;
        public $additionalPayments;
        public $discountPayments;
        public $biweeklyPayments;
        public $loan;
        public $campaign;

}
