<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscountType extends Model
{
    use HasFactory;
    use SoftDeletes;


    protected $fillable = [
        'description', 
        'value'
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function discountPayments()
    {
        return $this->hasMany(DiscountPayment::class);
    }
}
