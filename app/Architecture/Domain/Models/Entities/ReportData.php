<?php

namespace App\Architecture\Domain\Models\Entities;

use Spatie\LaravelData\Data;

class ReportData extends Data
{
    public PayRollData $payroll;
}