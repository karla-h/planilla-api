<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayRoll extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'accounting_salary',
        'real_salary',
        'employee_id',
        'campaign_id',
        'loan_id',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function biweeklyPayments()
    {
        return $this->hasMany(BiweeklyPayment::class);
    }

    public function additionalPayments()
    {
        return $this->hasMany(AdditionalPayment::class);
    }

    public function discountPayments()
    {
        return $this->hasMany(DiscountPayment::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
