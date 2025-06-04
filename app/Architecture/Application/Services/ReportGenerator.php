<?php

namespace App\Architecture\Application\Services;

use Illuminate\Support\Collection;

class ReportGenerator
{
    public function __construct(protected PayRollService $payrollService) {}

    public function generatePayRolls($headquarter, $pay_date)
    {
        $data = $this->payrollService->generatePayRolls($headquarter, $pay_date);
        return $data;
        // $response =  $data->map(function ($payroll) {
        //     // 🔹 Unir afiliaciones con descuentos
        //     $mergedDiscounts = collect($payroll['employee']['affiliations'])
        //         ->map(fn($affiliation) => [
        //             'description' => $affiliation['description'],
        //             'amount' => $affiliation['amount']
        //         ])
        //         ->merge(collect($payroll['discountPayments'])
        //         ->map(fn($discount) => [
        //             'description' => $discount['description'],
        //             'amount' => $discount['amount']
        //         ]))->values();

        //     // 🔹 Unir pagos adicionales
        //     $mergedAdditionals = collect($payroll['additionalPayments'])
        //         ->map(fn($payment) => [
        //             'description' => $payment['description'],
        //             'amount' => $payment['amount']
        //         ])->values();

        //     // 🔹 Extraer información de quincenas
        //     $biweeklyPayments = collect($payroll['biweeklyPayments'])
        //         ->map(fn($biweekly) => [
        //             'biweekly' => $biweekly['biweekly'],
        //             'real_amount' => $biweekly['real_amount'],
        //             'accounting_amount' => $biweekly['accounting_amount']
        //         ])->values();

        //     return [
        //         'name' => $payroll['employee']['name'],
        //         'dni' => $payroll['employee']['dni'],
        //         'pay_date' => $payroll['pay_date'],
        //         'accounting_salary' => $payroll['accounting_salary'],
        //         'real_salary' => $payroll['real_salary'],
        //         'discounts' => $mergedDiscounts,
        //         'additionals' => $mergedAdditionals,
        //         'biweekly_payments' => $biweeklyPayments,
        //     ];
        // });

        // return $response;
    }

    private function flattenDeductions(Collection $deductions): array
    {
        $flattened = [];

        foreach ($deductions as $deduction) {
            $flattened[$deduction['description']] = $deduction['amount'];
        }

        return $flattened;
    }

    public function flatten($headquarter, $pay_date)
    {
        $data = $this->payrollService->generatePayRolls($headquarter, $pay_date);

        $response =  $data->map(function ($payroll) {
            $mergedDeductions = collect();

            // Agregar afiliaciones como descuentos
            foreach ($payroll['employee']['affiliations'] as $affiliation) {
                $mergedDeductions->push([
                    'description' => $affiliation['description'],
                    'amount' => $affiliation['amount']
                ]);
            }

            // Agregar descuentos
            foreach ($payroll['discountPayments'] as $discount) {
                $mergedDeductions->push([
                    'description' => $discount['description'],
                    'amount' => $discount['amount']
                ]);
            }

            // Aplanar pagos adicionales
            $additionalPayments = collect($payroll['additionalPayments'])->mapWithKeys(function ($payment) {
                return [$payment['description'] => $payment['amount']];
            })->toArray();

            return array_merge([
                'name' => $payroll['employee']['name'],
                'dni' => $payroll['employee']['dni'],
                'pay_date' => $payroll['pay_date'],
                'accounting_salary' => $payroll['accounting_salary'],
                'real_salary' => $payroll['real_salary']
            ], $this->flattenDeductions($mergedDeductions), $additionalPayments);
        });

        return $response;
    }
    
}