<?php

namespace App\Architecture\Domain\Models\Entities;

use Spatie\LaravelData\Data;

class EmployeeData extends Data
{

    public $firstname;
    public $lastname;
    public $dni;
    public $born_date;
    public $email;
    public $account;
    public $phone;
    public $address;
    public HeadquarterData $headquarter;
    public $affiliations;
    public ContractData $contract;

}
