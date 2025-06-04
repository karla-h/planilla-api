<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    /** @use HasFactory<\Database\Factories\ContractFactory> */
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'employee_id',
        'hire_date',
        'termination_date',
        'termination_reason',
        'accounting_salary',
        'real_salary',
    ];
}
