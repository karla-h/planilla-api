<?php

namespace App\Architecture\Application\Services;

use Carbon\Carbon;

class PayrollCalculatorService
{
    /**
     * Calcular días trabajados en un periodo
     */
    public function calculateWorkedDays($hireDate, $periodStart, $periodEnd, $includeSundays = true)
    {
        $start = Carbon::parse($hireDate);
        $end = Carbon::parse($periodEnd);
        
        // Si la fecha de contratación es después del inicio del periodo, usar fecha de contratación
        if ($start->greaterThan(Carbon::parse($periodStart))) {
            $startDate = $start;
        } else {
            $startDate = Carbon::parse($periodStart);
        }
        
        $days = 0;
        $current = $startDate->copy();
        
        while ($current->lte($end)) {
            if ($includeSundays || !$current->isSunday()) {
                $days++;
            }
            $current->addDay();
        }
        
        return $days;
    }

    /**
     * Calcular salario proporcional
     */
    public function calculateProportionalSalary($salary, $workedDays, $totalDays = 30)
    {
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
            // Quincenal
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
     * Calcular pagos según tipo de contrato
     */
    public function calculatePayments($contract, $period, $additionalPayments = [], $discountPayments = [])
    {
        $workedDays = $this->calculateWorkedDays(
            $contract->hire_date,
            $period['start'],
            $period['end'],
            true // incluir domingos
        );

        $totalDays = $period['type'] === 'mensual' ? 30 : 15;

        // Salarios proporcionales
        $proportionalAccounting = $this->calculateProportionalSalary(
            $contract->accounting_salary,
            $workedDays,
            $totalDays
        );

        $proportionalReal = $this->calculateProportionalSalary(
            $contract->real_salary,
            $workedDays,
            $totalDays
        );

        // Para pagos mensuales
        if ($period['type'] === 'mensual') {
            return [
                'bank_transfer' => $proportionalAccounting, // Todo a transferencia
                'cash' => $proportionalReal - $proportionalAccounting, // Diferencia en efectivo
                'worked_days' => $workedDays,
                'total_days' => $totalDays,
                'proportional_accounting' => $proportionalAccounting,
                'proportional_real' => $proportionalReal
            ];
        }

        // Para pagos quincenales
        if ($period['type'] === 'quincena_1') {
            return [
                'bank_transfer' => $proportionalAccounting * 0.4, // 40% a transferencia
                'cash' => ($proportionalReal - $proportionalAccounting) * 0.4, // 40% de diferencia en efectivo
                'worked_days' => $workedDays,
                'total_days' => $totalDays,
                'proportional_accounting' => $proportionalAccounting,
                'proportional_real' => $proportionalReal
            ];
        }

        // Quincena 2
        if ($period['type'] === 'quincena_2') {
            $totalAdditions = collect($additionalPayments)->sum('amount');
            $totalDiscounts = collect($discountPayments)->sum('amount');
            
            return [
                'bank_transfer' => ($proportionalAccounting * 0.6) - $totalDiscounts + $totalAdditions,
                'cash' => ($proportionalReal - $proportionalAccounting) * 0.6,
                'worked_days' => $workedDays,
                'total_days' => $totalDays,
                'proportional_accounting' => $proportionalAccounting,
                'proportional_real' => $proportionalReal,
                'additions' => $totalAdditions,
                'discounts' => $totalDiscounts
            ];
        }

        return null;
    }
}