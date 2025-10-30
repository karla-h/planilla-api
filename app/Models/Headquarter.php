<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Headquarter extends Model
{
    use HasFactory;
    use SoftDeletes;


    protected $fillable = [
        'name', 
        'address', 
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
