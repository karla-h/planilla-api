<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hire_date',
        'termination_date',
        'termination_reason',
        'accounting_salary',
        'real_salary',
        'payment_type',
        'status_code',
        'employee_id'
    ];

    protected $casts = [
        'hire_date' => 'date',
        'termination_date' => 'date',
        'accounting_salary' => 'decimal:2',
        'real_salary' => 'decimal:2',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function isActive()
    {
        return $this->status_code === 'active';
    }

    public function terminate($reason = null)
    {
        $this->update([
            'termination_date' => now(),
            'termination_reason' => $reason,
            'status_code' => 'terminated'
        ]);
    }

    public function suspend()
    {
        $this->update(['status_code' => 'suspended']);
    }

    public function activate()
    {
        $this->update(['status_code' => 'active']);
    }
}