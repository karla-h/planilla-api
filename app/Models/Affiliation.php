<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Affiliation extends Model
{
    /** @use HasFactory<\Database\Factories\AffiliationsFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'description',
        'percent'
    ];

    public function employeeAffiliations() {
        return $this->hasMany(EmployeeAffiliation::class);
    }
}
