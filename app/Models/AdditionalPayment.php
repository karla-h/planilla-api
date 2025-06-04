<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdditionalPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'pay_roll_id',
        'payment_type_id',
        'amount',
        'quantity',
        'biweek',
        'pay_card'
    ];

    public function payRoll()
    {
        return $this->belongsTo(PayRoll::class);
    }

    public function paymentType() {
        return $this->belongsTo(PaymentType::class);
    }
}
