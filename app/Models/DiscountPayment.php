<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscountPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'pay_roll_id',
        'discount_type_id',
        'amount', 
        'quantity',
        'biweek',
        'pay_card'
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

    public function discountType()
    {
        return $this->belongsTo(DiscountType::class);
    }
}
