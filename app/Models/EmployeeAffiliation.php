<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeAffiliation extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeAffiliationsFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'affiliation_id',
        'percent', 
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function affiliation()
    {
        return $this->belongsTo(Affiliation::class);
    }
}
