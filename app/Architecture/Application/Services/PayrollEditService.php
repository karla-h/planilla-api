<?php

namespace App\Architecture\Application\Services;

use App\Models\PayRoll;
use App\Models\BiweeklyPayment;
use App\Models\AdditionalPayment;
use App\Models\DiscountPayment;
use App\Models\PaymentType;
use App\Models\Contract;
use App\Models\DiscountType;
use Illuminate\Support\Facades\Log;

class PayrollEditService
{
    public function canEditPayroll($payrollId, $biweekly = null)
    {
        $payroll = PayRoll::find($payrollId);

        if (!$payroll) {
            return ['can_edit' => false, 'reason' => 'Planilla no encontrada'];
        }

        if ($payroll->status === 'closed') {
            return [
                'can_edit' => false,
                'reason' => 'Planilla cerrada',
                'can_reopen' => true
            ];
        }

        return [
            'can_edit' => true,
            'reason' => 'Edición permitida',
            'payroll_status' => $payroll->status
        ];
    }

    public function createOrUpdateAdvance($payrollId, $data, $advanceId = null)
    {
        try {
            $canEdit = $this->canEditPayroll($payrollId, $data['biweek'] ?? null);
            if (!$canEdit['can_edit']) {
                return ['status' => 400, 'message' => $canEdit['reason']];
            }

            $advanceType = DiscountType::firstOrCreate(
                ['description' => 'Adelanto de Sueldo'],
                ['code' => 'ADELANTO']
            );

            if ($advanceId) {
                $advance = DiscountPayment::where('id', $advanceId)->where('is_advance', true)->first();
                if (!$advance) return ['status' => 404, 'message' => 'Adelanto no encontrado'];

                $advance->update([
                    'amount' => $data['amount'],
                    'pay_card' => $data['pay_card'] ?? 2,
                    'biweek' => $data['biweek'] ?? null,
                    'advance_date' => $data['advance_date'] ?? now(),
                ]);
                $message = 'Adelanto actualizado';
            } else {
                $advance = DiscountPayment::create([
                    'pay_roll_id' => $payrollId,
                    'discount_type_id' => $advanceType->id,
                    'amount' => $data['amount'],
                    'quantity' => 1,
                    'biweek' => $data['biweek'] ?? null,
                    'pay_card' => $data['pay_card'] ?? 2,
                    'is_advance' => true,
                    'advance_date' => $data['advance_date'] ?? now(),
                    'deducted_in_biweekly_id' => null
                ]);
                $message = 'Adelanto creado';
            }

            $this->regenerateAffectedPayments($payrollId, $data['biweek'] ?? null);

            return ['status' => 200, 'message' => $message, 'data' => $advance];
        } catch (\Exception $e) {
            return ['status' => 500, 'message' => 'Error: ' . $e->__toString()];
        }
    }

    public function regenerateAffectedPayments($payrollId, $biweekly = null)
    {
        $payroll = PayRoll::with(['biweeklyPayments'])->find($payrollId);
        if (!$payroll || $payroll->status === 'closed') return;

        if ($biweekly) {
            $biweeklyPayment = $payroll->biweeklyPayments->where('biweekly', $biweekly)->first();
            if ($biweeklyPayment) $this->regenerateSinglePayment($payrollId, $biweekly);
        } else {
            foreach ([1, 2] as $biweek) {
                $biweeklyPayment = $payroll->biweeklyPayments->where('biweekly', $biweek)->first();
                if ($biweeklyPayment) $this->regenerateSinglePayment($payrollId, $biweek);
            }
        }
    }

    private function regenerateSinglePayment($payrollId, $biweekly)
    {
        $payroll = PayRoll::with([
            'employee.activeContract',
            'additionalPayments',
            'discountPayments',
            'loan',
            'campaign'
        ])->find($payrollId);

        $calculator = new \App\Architecture\Application\Services\PayrollCalculatorService();
        $contract = $payroll->employee->activeContract;
        $year = $payroll->period_start->year;
        $month = $payroll->period_start->month;

        $periods = $calculator->getPaymentPeriods($contract->payment_type, $year, $month);
        $targetPeriod = $biweekly == 1 ? $periods[0] : $periods[1];

        $calculation = $calculator->calculatePayments(
            $contract,
            $targetPeriod,
            $payroll->additionalPayments->toArray(),
            $payroll->discountPayments->toArray(),
            $payroll->loan,
            $payroll->campaign,
            $payrollId
        );

        BiweeklyPayment::where('pay_roll_id', $payrollId)
            ->where('biweekly', $biweekly)
            ->update([
                'accounting_amount' => $calculation['bank_transfer'],
                'real_amount' => $calculation['cash'],
                'discounts' => $calculation['discounts'] + $calculation['advances_applied'],
                'additionals' => $calculation['additionals'],
                'updated_at' => now()
            ]);
    }

    public function calculateMaxAdvance($payrollId, $biweekly, $payCard = null)
    {
        try {
            $payroll = PayRoll::with([
                'employee.activeContract',
                'additionalPayments' => function ($q) use ($biweekly) {
                    $q->where('biweek', $biweekly);
                },
                'discountPayments' => function ($q) use ($biweekly) {
                    $q->where('biweek', $biweekly);
                }
            ])->find($payrollId);

            if (!$payroll) {
                Log::error("Planilla no encontrada: {$payrollId}");
                return 0;
            }

            if (!$payroll->employee->activeContract) {
                Log::error("Contrato activo no encontrado para empleado: {$payroll->employee->id}");
                return 0;
            }

            $contract = $payroll->employee->activeContract;

            // ✅ SEPARAR ADELANTOS DE DESCUENTOS REGULARES
            $regularDiscounts = $payroll->discountPayments->where('is_advance', false);
            $advancePayments = $payroll->discountPayments->where('is_advance', true);

            Log::info("Cálculo máximo adelanto", [
                'payroll_id' => $payrollId,
                'biweekly' => $biweekly,
                'contract_type' => $contract->payment_type,
                'salary' => $contract->real_salary,
                'regular_discounts_count' => $regularDiscounts->count(),
                'advance_payments_count' => $advancePayments->count()
            ]);

            // Calcular base según tipo de contrato
            if ($contract->payment_type === 'mensual') {
                $baseBank = $contract->accounting_salary / 2; // Mitad para quincena
                $baseCash = ($contract->real_salary - $contract->accounting_salary) / 2;
            } else {
                if ($biweekly == 1) {
                    $baseBank = $contract->accounting_salary * 0.4;
                    $baseCash = ($contract->real_salary - $contract->accounting_salary) * 0.4;
                } else {
                    $baseBank = $contract->accounting_salary * 0.6;
                    $baseCash = ($contract->real_salary - $contract->accounting_salary) * 0.6;
                }
            }

            // Calcular adicionales y descuentos
            $additionalsBank = $payroll->additionalPayments->where('pay_card', 1)->sum('amount');
            $additionalsCash = $payroll->additionalPayments->where('pay_card', 2)->sum('amount');
            $discountsBank = $regularDiscounts->where('pay_card', 1)->sum('amount');
            $discountsCash = $regularDiscounts->where('pay_card', 2)->sum('amount');

            // Calcular disponible
            $availableBank = max(0, $baseBank + $additionalsBank - $discountsBank);
            $availableCash = max(0, $baseCash + $additionalsCash - $discountsCash);

            // Límite del 30% del salario base
            $salaryLimit = $contract->real_salary * 0.3;
            $availableBank = min($availableBank, $salaryLimit);
            $availableCash = min($availableCash, $salaryLimit);

            $result = 0;
            if ($payCard == 1) $result = $availableBank;
            elseif ($payCard == 2) $result = $availableCash;
            else $result = $availableBank + $availableCash;

            Log::info("Resultado cálculo máximo adelanto", [
                'available_bank' => $availableBank,
                'available_cash' => $availableCash,
                'salary_limit' => $salaryLimit,
                'final_result' => $result
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error("Error en calculateMaxAdvance: " . $e->getMessage());
            return 0;
        }
    }

    public function getSuspendedDays($contractId, $periodStart, $periodEnd)
    {
        $contract = Contract::find($contractId);
        if (!$contract || empty($contract->suspension_periods)) return 0;

        $suspendedDays = 0;
        $suspensionPeriods = json_decode($contract->suspension_periods, true);
        $periodStart = \Carbon\Carbon::parse($periodStart);
        $periodEnd = \Carbon\Carbon::parse($periodEnd);

        foreach ($suspensionPeriods as $period) {
            $suspendStart = \Carbon\Carbon::parse($period['start']);
            $suspendEnd = \Carbon\Carbon::parse($period['end']);

            $intersectStart = max($periodStart, $suspendStart);
            $intersectEnd = min($periodEnd, $suspendEnd);

            if ($intersectStart->lessThanOrEqualTo($intersectEnd)) {
                $suspendedDays += $intersectStart->diffInDays($intersectEnd) + 1;
            }
        }

        return $suspendedDays;
    }
}
