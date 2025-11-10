<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'firstname',
        'lastname',
        'dni',
        'born_date',
        'email',
        'account',
        'phone',
        'address',
        'department',
        'headquarter_id',
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function headquarter()
    {
        return $this->belongsTo(Headquarter::class);
    }

    public function payRolls()
    {
        return $this->hasMany(PayRoll::class);
    }

    public function employeeAffiliations()
    {
        return $this->hasMany(EmployeeAffiliation::class);
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    // En app/Models/Employee.php
    public function activeContract()
    {
        return $this->hasOne(Contract::class)
            ->where('status_code', 'active')
            ->latest(); // O ->where('status_code', 'active')->first()
    }

    /**
     * Relación con loans por DNI
     */
    public function loans()
    {
        return $this->hasMany(Loan::class, 'employee', 'dni');
    }

    /**
     * Obtener préstamo activo
     */
    public function activeLoan()
    {
        return $this->hasOne(Loan::class, 'employee', 'dni')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    public function getActiveContractAttribute()
{
    return $this->activeContract()->first();
}


}
