<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentType extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'description', 
        'value'
    ];

    public function additionalPayments()
    {
        return $this->hasMany(AdditionalPayment::class);
    }
}
