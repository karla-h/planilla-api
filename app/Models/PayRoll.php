<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayRoll extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_DRAFT = 'draft';
    const STATUS_OPEN = 'open'; 
    const STATUS_PARTIAL = 'partial';
    const STATUS_CLOSED = 'closed';
    const STATUS_PAID = 'paid';
    const STATUS_LOCKED = 'locked';

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

    // Nuevos métodos de estado
    public function isDraft()
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isOpen()
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isPartial()
    {
        return $this->status === self::STATUS_PARTIAL;
    }

    public function isClosed()
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isLocked()
    {
        return $this->status === self::STATUS_LOCKED;
    }

    public function canEdit()
    {
        return $this->canEditAdditions() || $this->canEditDiscounts();
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

    public function getPermissions()
    {
        return [
            self::STATUS_DRAFT => [
                'edit_additions' => true,
                'edit_discounts' => true,
                'generate_payments' => false,
                'edit_payments' => false,
                'delete_payments' => false,
                'can_close' => false
            ],
            self::STATUS_OPEN => [
                'edit_additions' => true,
                'edit_discounts' => true, 
                'generate_payments' => true,
                'edit_payments' => true,
                'delete_payments' => true,
                'can_close' => false
            ],
            self::STATUS_PARTIAL => [
                'edit_additions' => true,
                'edit_discounts' => true,
                'generate_payments' => true,
                'edit_payments' => true,
                'delete_payments' => true,
                'can_close' => false
            ],
            self::STATUS_CLOSED => [
                'edit_additions' => false,
                'edit_discounts' => false,
                'generate_payments' => false,
                'edit_payments' => false,
                'delete_payments' => false,
                'can_close' => true
            ],
            self::STATUS_PAID => [
                'edit_additions' => false,
                'edit_discounts' => false,
                'generate_payments' => false,
                'edit_payments' => false,
                'delete_payments' => false,
                'can_close' => true
            ],
            self::STATUS_LOCKED => [
                'edit_additions' => false,
                'edit_discounts' => false,
                'generate_payments' => false,
                'edit_payments' => false,
                'delete_payments' => false,
                'can_close' => false
            ]
        ];
    }

    public function canEditAdditions()
    {
        return $this->getPermissions()[$this->status]['edit_additions'];
    }

    public function canEditDiscounts()
    {
        return $this->getPermissions()[$this->status]['edit_discounts'];
    }

    public function canGeneratePayments()
    {
        return $this->getPermissions()[$this->status]['generate_payments'];
    }

    public function canEditPayments()
    {
        return $this->getPermissions()[$this->status]['edit_payments'];
    }

    public function canDeletePayments()
    {
        return $this->getPermissions()[$this->status]['delete_payments'];
    }

    public function canClose()
    {
        return $this->getPermissions()[$this->status]['can_close'];
    }

    public function getAllPermissions()
    {
        return $this->getPermissions()[$this->status];
    }
}