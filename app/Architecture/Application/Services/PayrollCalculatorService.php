<?php

namespace App\Architecture\Application\Services;

use App\Models\DiscountPayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PayrollCalculatorService
{
    /**
     * Calcular días trabajados en un periodo - CORREGIDO
     */
    public function calculateWorkedDays($hireDate, $periodStart, $periodEnd, $includeSundays = true)
    {
        $hire = Carbon::parse($hireDate);
        $periodStartDate = Carbon::parse($periodStart);
        $periodEndDate = Carbon::parse($periodEnd);

        if ($hire->greaterThan($periodStartDate)) {
            $startDate = $hire;
        } else {
            $startDate = $periodStartDate;
        }

        if ($hire->greaterThan($periodEndDate)) {
            return 0;
        }

        $days = 0;
        $current = $startDate->copy();

        while ($current->lte($periodEndDate)) {
            if ($includeSundays || !$current->isSunday()) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    /**
     * Determinar si necesita cálculo proporcional
     */
    private function needsProportionalCalculation($hireDate, $periodStart, $periodType)
    {
        $hire = Carbon::parse($hireDate);
        $periodStartDate = Carbon::parse($periodStart);
        return $hire->greaterThan($periodStartDate);
    }

    /**
     * Calcular salario proporcional
     */
    public function calculateProportionalSalary($salary, $workedDays, $totalDays = 30)
    {
        if ($workedDays <= 0) return 0;
        return ($salary / $totalDays) * $workedDays;
    }

    /**
     * Obtener periodos de pago según tipo
     */
    public function getPaymentPeriods($paymentType, $year, $month)
    {
        if ($paymentType === 'mensual') {
            return [
                [
                    'start' => Carbon::create($year, $month, 1)->format('Y-m-d'),
                    'end' => Carbon::create($year, $month, 1)->endOfMonth()->format('Y-m-d'),
                    'type' => 'mensual'
                ]
            ];
        } else {
            return [
                [
                    'start' => Carbon::create($year, $month, 1)->format('Y-m-d'),
                    'end' => Carbon::create($year, $month, 15)->format('Y-m-d'),
                    'type' => 'quincena_1'
                ],
                [
                    'start' => Carbon::create($year, $month, 16)->format('Y-m-d'),
                    'end' => Carbon::create($year, $month, 1)->endOfMonth()->format('Y-m-d'),
                    'type' => 'quincena_2'
                ]
            ];
        }
    }

    /**
     * Calcular additional payments separados por pay_card
     */
    private function calculateAdditionalPayments($additionalPayments, $biweekly)
    {
        Log::info('Calculando additional payments', [
            'payments_count' => is_countable($additionalPayments) ? count($additionalPayments) : 0,
            'biweekly' => $biweekly
        ]);

        if (empty($additionalPayments)) {
            return ['bank' => 0, 'cash' => 0, 'total' => 0];
        }

        $bankAdditionals = 0;
        $cashAdditionals = 0;

        foreach ($additionalPayments as $payment) {
            $amount = is_object($payment) ? $payment->amount : ($payment['amount'] ?? 0);
            $quantity = is_object($payment) ? $payment->quantity : ($payment['quantity'] ?? 1);
            $payCard = is_object($payment) ? $payment->pay_card : ($payment['pay_card'] ?? 1);
            $paymentBiweek = is_object($payment) ? $payment->biweek : ($payment['biweek'] ?? null);

            $appliesToBiweekly = !$paymentBiweek || $paymentBiweek == $biweekly;

            if ($appliesToBiweekly) {
                $subtotal = $amount * $quantity;

                if ($payCard == 1) {
                    $bankAdditionals += $subtotal;
                } else {
                    $cashAdditionals += $subtotal;
                }
            }
        }

        $result = [
            'bank' => $bankAdditionals,
            'cash' => $cashAdditionals,
            'total' => $bankAdditionals + $cashAdditionals
        ];

        Log::info('Additional payments calculados', $result);
        return $result;
    }

    /**
     * Calcular discount payments separados por pay_card
     */
    private function calculateDiscountPayments($discountPayments, $biweekly)
    {
        Log::info('Calculando discount payments', [
            'payments_count' => is_countable($discountPayments) ? count($discountPayments) : 0,
            'biweekly' => $biweekly
        ]);

        if (empty($discountPayments)) {
            return ['bank' => 0, 'cash' => 0, 'total' => 0];
        }

        $bankDiscounts = 0;
        $cashDiscounts = 0;

        foreach ($discountPayments as $payment) {
            $amount = is_object($payment) ? $payment->amount : ($payment['amount'] ?? 0);
            $quantity = is_object($payment) ? $payment->quantity : ($payment['quantity'] ?? 1);
            $payCard = is_object($payment) ? $payment->pay_card : ($payment['pay_card'] ?? 1);
            $paymentBiweek = is_object($payment) ? $payment->biweek : ($payment['biweek'] ?? null);

            $appliesToBiweekly = !$paymentBiweek || $paymentBiweek == $biweekly;

            if ($appliesToBiweekly) {
                $subtotal = $amount * $quantity;

                if ($payCard == 1) {
                    $bankDiscounts += $subtotal;
                } else {
                    $cashDiscounts += $subtotal;
                }
            }
        }

        $result = [
            'bank' => $bankDiscounts,
            'cash' => $cashDiscounts,
            'total' => $bankDiscounts + $cashDiscounts
        ];

        Log::info('Discount payments calculados', $result);
        return $result;
    }

    /**
     * Calcular loan payments
     */
    private function calculateLoanPayments($loan, $biweekly)
    {
        if (!$loan) {
            return ['bank' => 0, 'cash' => 0, 'total' => 0];
        }

        $loanBiweek = $loan->biweek ?? null;
        $appliesToBiweekly = !$loanBiweek || $loanBiweek == $biweekly;

        if (!$appliesToBiweekly) {
            return ['bank' => 0, 'cash' => 0, 'total' => 0];
        }

        $amount = $loan->amount ?? 0;
        $payCard = $loan->pay_card ?? 1;

        if ($payCard == 1) {
            return ['bank' => $amount, 'cash' => 0, 'total' => $amount];
        } else {
            return ['bank' => 0, 'cash' => $amount, 'total' => $amount];
        }
    }

    /**
     * Calcular pagos según tipo de contrato - CORREGIDO (parámetro fix)
     */
    public function calculatePayments($contract, $period, $additionalPayments = [], $discountPayments = [], $loan = null, $campaign = null, $payrollId = null)
    {
        Log::info('=== INICIANDO CÁLCULO DE PAGOS ===', [
            'period' => $period,
            'contract_type' => $contract->payment_type ?? 'N/A',
            'accounting_salary' => $contract->accounting_salary,
            'real_salary' => $contract->real_salary,
            'hire_date' => $contract->hire_date,
            'has_campaign' => $campaign ? 'Sí' : 'No'
        ]);

        $needsProportional = $this->needsProportionalCalculation(
            $contract->hire_date,
            $period['start'],
            $period['type']
        );

        $workedDays = $this->calculateWorkedDays(
            $contract->hire_date,
            $period['start'],
            $period['end'],
            true
        );

        $totalDays = $period['type'] === 'mensual' ? 30 : 15;
        $biweekly = $period['type'] === 'quincena_1' ? 1 : ($period['type'] === 'quincena_2' ? 2 : null);

        $additionals = $this->calculateAdditionalPayments($additionalPayments, $biweekly);
        $discounts = $this->calculateDiscountPayments($discountPayments, $biweekly);
        $loanPayments = $this->calculateLoanPayments($loan, $biweekly);
        $campaignPayments = $this->calculateCampaignPayments($campaign, $biweekly);

        Log::info('Resumen de cálculos', [
            'worked_days' => $workedDays,
            'total_days' => $totalDays,
            'needs_proportional' => $needsProportional,
            'additionals' => $additionals,
            'discounts' => $discounts,
            'loan' => $loanPayments,
            'campaign' => $campaignPayments
        ]);

        // ========== PAGOS MENSUALES ==========
        if ($period['type'] === 'mensual') {
            if ($needsProportional && $workedDays > 0) {
                $proportionalAccounting = $this->calculateProportionalSalary($contract->accounting_salary, $workedDays, $totalDays);
                $proportionalReal = $this->calculateProportionalSalary($contract->real_salary, $workedDays, $totalDays);
                $diferenciaProporcional = $proportionalReal - $proportionalAccounting;

                $bankTransfer = $proportionalAccounting + $additionals['bank'] + $campaignPayments['bank'] - $discounts['bank'] - $loanPayments['bank'];
                $cash = $diferenciaProporcional + $additionals['cash'] + $campaignPayments['cash'] - $discounts['cash'] - $loanPayments['cash'];
            } else {
                $diferenciaRealContable = $contract->real_salary - $contract->accounting_salary;
                $bankTransfer = $contract->accounting_salary + $additionals['bank'] + $campaignPayments['bank'] - $discounts['bank'] - $loanPayments['bank'];
                $cash = $diferenciaRealContable + $additionals['cash'] + $campaignPayments['cash'] - $discounts['cash'] - $loanPayments['cash'];
            }

            $totalPago = $bankTransfer + $cash;

            $result = [
                'bank_transfer' => max(0, $bankTransfer), // ✅ EVITAR VALORES NEGATIVOS
                'cash' => max(0, $cash), // ✅ EVITAR VALORES NEGATIVOS
                'total_pago' => max(0, $totalPago), // ✅ EVITAR VALORES NEGATIVOS
                'worked_days' => $workedDays,
                'total_days' => $totalDays,
                'needs_proportional' => $needsProportional,
                'additionals' => $additionals['total'],
                'discounts' => $discounts['total'] + $loanPayments['total'],
                'campaign' => $campaignPayments['total'],
                'additionals_detail' => $additionals,
                'discounts_detail' => $discounts,
                'loan_detail' => $loanPayments,
                'campaign_detail' => $campaignPayments
            ];

            Log::info('Resultado mensual', $result);
            return $result;
        }

        // ========== PAGOS QUINCENALES ==========
        if ($period['type'] === 'quincena_1') {
            if ($needsProportional && $workedDays > 0) {
                $proportionalAccounting = $this->calculateProportionalSalary($contract->accounting_salary * 0.4, $workedDays, $totalDays);
                $diferenciaRealContable = $contract->real_salary - $contract->accounting_salary;
                $proportionalDiferencia = $this->calculateProportionalSalary($diferenciaRealContable * 0.4, $workedDays, $totalDays);

                $bankTransfer = $proportionalAccounting + $additionals['bank'] + $campaignPayments['bank'] - $discounts['bank'] - $loanPayments['bank'];
                $cash = $proportionalDiferencia + $additionals['cash'] + $campaignPayments['cash'] - $discounts['cash'] - $loanPayments['cash'];
            } else {
                $diferenciaRealContable = $contract->real_salary - $contract->accounting_salary;
                $bankTransfer = ($contract->accounting_salary * 0.4) + $additionals['bank'] + $campaignPayments['bank'] - $discounts['bank'] - $loanPayments['bank'];
                $cash = ($diferenciaRealContable * 0.4) + $additionals['cash'] + $campaignPayments['cash'] - $discounts['cash'] - $loanPayments['cash'];
            }

            $totalPago = $bankTransfer + $cash;

            // CALCULAR ADELANTOS
            $advances = ['bank' => 0, 'cash' => 0, 'total' => 0];
            if ($payrollId && $biweekly) {
                $advances = $this->calculateAdvancePayments($payrollId, $biweekly);
            }

            // APLICAR ADELANTOS CON VALIDACIÓN
            $finalBank = max(0, $bankTransfer - $advances['bank']); // ✅ EVITAR NEGATIVOS
            $finalCash = max(0, $cash - $advances['cash']); // ✅ EVITAR NEGATIVOS

            $warnings = [];
            if ($bankTransfer - $advances['bank'] < 0) $warnings[] = "Adelanto mayor a transferencia bancaria";
            if ($cash - $advances['cash'] < 0) $warnings[] = "Adelanto mayor a efectivo";

            $result = [
                'bank_transfer' => $finalBank,
                'cash' => $finalCash,
                'total_pago' => $finalBank + $finalCash,
                'advances_applied' => $advances['total'],
                'advances_detail' => $advances,
                'warnings' => $warnings,
                'worked_days' => $workedDays,
                'total_days' => $totalDays,
                'needs_proportional' => $needsProportional,
                'additionals' => $additionals['total'],
                'discounts' => $discounts['total'] + $loanPayments['total'],
                'campaign' => $campaignPayments['total'],
                'additionals_detail' => $additionals,
                'discounts_detail' => $discounts,
                'loan_detail' => $loanPayments,
                'campaign_detail' => $campaignPayments
            ];

            Log::info('Resultado quincena 1', $result);
            return $result;
        }

        // Quincena 2
        if ($period['type'] === 'quincena_2') {
            if ($needsProportional && $workedDays > 0) {
                $proportionalAccounting = $this->calculateProportionalSalary($contract->accounting_salary * 0.6, $workedDays, $totalDays);
                $diferenciaRealContable = $contract->real_salary - $contract->accounting_salary;
                $proportionalDiferencia = $this->calculateProportionalSalary($diferenciaRealContable * 0.6, $workedDays, $totalDays);

                $bankTransfer = $proportionalAccounting + $additionals['bank'] + $campaignPayments['bank'] - $discounts['bank'] - $loanPayments['bank'];
                $cash = $proportionalDiferencia + $additionals['cash'] + $campaignPayments['cash'] - $discounts['cash'] - $loanPayments['cash'];
            } else {
                $diferenciaRealContable = $contract->real_salary - $contract->accounting_salary;
                $bankTransfer = ($contract->accounting_salary * 0.6) + $additionals['bank'] + $campaignPayments['bank'] - $discounts['bank'] - $loanPayments['bank'];
                $cash = ($diferenciaRealContable * 0.6) + $additionals['cash'] + $campaignPayments['cash'] - $discounts['cash'] - $loanPayments['cash'];
            }

            $totalPago = $bankTransfer + $cash;

            $result = [
                'bank_transfer' => max(0, $bankTransfer), // ✅ EVITAR NEGATIVOS
                'cash' => max(0, $cash), // ✅ EVITAR NEGATIVOS
                'total_pago' => max(0, $totalPago), // ✅ EVITAR NEGATIVOS
                'worked_days' => $workedDays,
                'total_days' => $totalDays,
                'needs_proportional' => $needsProportional,
                'additionals' => $additionals['total'],
                'discounts' => $discounts['total'] + $loanPayments['total'],
                'campaign' => $campaignPayments['total'],
                'additionals_detail' => $additionals,
                'discounts_detail' => $discounts,
                'loan_detail' => $loanPayments,
                'campaign_detail' => $campaignPayments
            ];

            Log::info('Resultado quincena 2', $result);
            return $result;
        }

        return null;
    }

    private function calculateCampaignPayments($campaign, $biweekly)
    {
        if (!$campaign) {
            return ['bank' => 0, 'cash' => 0, 'total' => 0];
        }

        $campaignBiweek = $campaign->biweek ?? null;
        $appliesToBiweekly = !$campaignBiweek || $campaignBiweek == $biweekly;

        if (!$appliesToBiweekly) {
            return ['bank' => 0, 'cash' => 0, 'total' => 0];
        }

        $amount = $campaign->amount ?? 0;
        $payCard = $campaign->pay_card ?? 1;

        if ($payCard == 1) {
            return ['bank' => $amount, 'cash' => 0, 'total' => $amount];
        } else {
            return ['bank' => 0, 'cash' => $amount, 'total' => $amount];
        }
    }

    private function calculateAdvancePayments($payrollId, $biweekly)
    {
        $advances = DiscountPayment::where('pay_roll_id', $payrollId)
        ->where('is_advance', true)
        ->where('biweek', $biweekly)
        ->whereNull('deducted_in_biweekly_id')
        ->get();

    $bankAdvances = $advances->where('pay_card', 1)->sum('amount');
    $cashAdvances = $advances->where('pay_card', 2)->sum('amount');

    return [
        'bank' => $bankAdvances,
        'cash' => $cashAdvances,
        'total' => $bankAdvances + $cashAdvances,
        'details' => $advances
    ];
    }
}