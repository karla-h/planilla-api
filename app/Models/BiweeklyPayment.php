<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BiweeklyPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'biweekly', 
        'biweekly_date', 
        'accounting_amount', 
        'real_amount', 
        'discounts',
        'additionals',
        'pay_roll_id'
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function payroll()
    {
        return $this->belongsTo(PayRoll::class);
    }
}
