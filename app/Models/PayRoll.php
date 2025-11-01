<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayRoll extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_OPEN = 'open';
    const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'accounting_salary',
        'real_salary',
        'employee_id',
        'loan_id',
        'campaign_id',
        'status',
        'period_start',
        'period_end'
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    // Relaciones existentes...
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function additionalPayments()
    {
        return $this->hasMany(AdditionalPayment::class);
    }

    public function discountPayments()
    {
        return $this->hasMany(DiscountPayment::class);
    }

    public function biweeklyPayments()
    {
        return $this->hasMany(BiweeklyPayment::class);
    }

    public function getPermissions()
    {
        return [
            self::STATUS_OPEN => [       // ✅ MÁXIMA FLEXIBILIDAD
                'edit_additions' => true,
                'edit_discounts' => true, 
                'generate_payments' => true,
                'edit_payments' => true,
                'delete_payments' => true,
                'edit_planilla' => true,     // Editar salarios, periodos
                'apply_loans' => true,       // Aplicar préstamos
                'apply_campaigns' => true,   // Aplicar campañas
                'recalculate_all' => true,   // Recalcular todo
                'can_close' => true,
                'can_reopen' => false
            ],
            self::STATUS_CLOSED => [     // ❌ SOLO LECTURA
                'edit_additions' => false,
                'edit_discounts' => false,
                'generate_payments' => false, 
                'edit_payments' => false,
                'delete_payments' => false,
                'edit_planilla' => false,
                'apply_loans' => false,
                'apply_campaigns' => false,
                'recalculate_all' => false,
                'can_close' => false,
                'can_reopen' => true      // ← Puede reabrir si se equivocó
            ]
        ];
    }

    public function isOpen()
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed()
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function canEdit()
    {
        return $this->isOpen();
    }

    public function getPaymentType()
    {
        return $this->employee->activeContract->payment_type ?? 'quincenal';
    }

    public function getExpectedBiweeklyPayments()
    {
        return $this->getPaymentType() === 'quincenal' ? 2 : 1;
    }

    public function getCompletedBiweeklyPayments()
    {
        return $this->biweeklyPayments()->count();
    }

    public function canEditAdditions()
    {
        return $this->isOpen();
    }

    public function canEditDiscounts()
    {
        return $this->isOpen();
    }

    public function canGeneratePayments()
    {
        return $this->isOpen();
    }

    public function canEditPayments()
    {
        return $this->isOpen();
    }

    public function canDeletePayments()
    {
        return $this->isOpen();
    }

    public function canClose()
    {
        return $this->isOpen();
    }

    public function canRecalculate()
    {
        return $this->isOpen();
    }

    public function getAllPermissions()
    {
        return $this->getPermissions()[$this->status];
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}