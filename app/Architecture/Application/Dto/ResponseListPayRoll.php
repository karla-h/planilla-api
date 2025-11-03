<?php

namespace App\Architecture\Application\Dto;

use Spatie\LaravelData\Data;

class ResponseListPayRoll extends Data
{
    public function __construct(
        public ?int $id,
        public string $name,
        public string $dni,
        public string $headquarter,
        public string $pay_date,
        public float $accounting_salary,
        public float $real_salary,
        public float $discounts,
        public float $additionals,
        public array $biweeklyPayments,
        public ?string $status
    ) {}
}