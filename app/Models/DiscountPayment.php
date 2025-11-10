<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscountPayment extends Model
{
    use HasFactory;

     protected $fillable = [
        'amount', 'quantity', 'biweek', 'pay_card',
        'is_advance', 'advance_date', 'deducted_in_biweekly_id',
        'discount_type_id', 'pay_roll_id'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function payRoll()
    {
        return $this->belongsTo(PayRoll::class, 'pay_roll_id');
    }

    public function discountType()
    {
        return $this->belongsTo(DiscountType::class);
    }

    public function scopeAdvances($query)
    {
        return $query->where('is_advance', true);
    }
    
    public function scopeRegularDiscounts($query)
    {
        return $query->where('is_advance', false);
    }
}
