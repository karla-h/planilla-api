<?php

namespace App\Architecture\Application\Dto;

use Spatie\LaravelData\Data;

class BiweeklyReportByBiweek extends Data
{
    public string $name;
    public string $dni;
    public string $headquarter;
    public string $pay_date;
    public $biweeklyPayments;
    public $campaigns;
}
