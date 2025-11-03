<?php

namespace App\Architecture\Application\Dto;

class CreateEmployeeDto extends \Spatie\LaravelData\Data
{
    /**
     * @param CreateAffiliationDto[] $affiliations
     */
    public function __construct(
        public   string $firstname,
        public   string $lastname,
        public   string $dni,
        public   string $born_date,
        public   ?string $email,
        public   ?string $phone,
        public   ?string $address,
        public   ?string $account,
        public   int $headquarter_id,
        /** @var CreateAffiliationDto[] */
        public   array $affiliations,
        public   CreateContractDto $contracts,
    ) {
    }
}

class CreateAffiliationDto extends \Spatie\LaravelData\Data
{
    public function __construct(
        public   int $affiliation_id,
        public   float $percent
    ) {
    }
}

class CreateContractDto extends \Spatie\LaravelData\Data
{
    public function __construct(
        public   string $hire_date,
        public   float $accounting_salary,
        public   float $real_salary,
        public   string $payment_type,
        public   string $status_code
    ) {
    }
}