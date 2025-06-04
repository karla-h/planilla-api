<?php

namespace App\Architecture\Infrastructure\Repositories;

use App\Architecture\Domain\Models\Entities\BiweeklyPaymentData;
use App\Models\BiweeklyPayment;
use App\Models\Employee;
use App\Models\Headquarter;
use App\Models\Loan;
use App\Models\PayRoll;

class BiweeklyPaymentRepository implements IBaseRepository
{
    public function __construct(protected PayRollRepository $payRollRepository) {}

    public function create($data)
    {
        try {
            $employee = Employee::where('dni', $data['dni'])->first();
            $currentYear = now()->year;
            $currentMonth = now()->month;

            $payroll = PayRoll::with(['employee', 'additionalPayments', 'discountPayments', 'biweeklyPayments'])
                ->where('employee_id', $employee->id)
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $currentMonth)
                ->firstOrFail();

            if ($payroll->biweeklyPayments->count() >= 2) {
                return [
                    'message' => 'The employee already has two biweekly payments for the given month and year',
                    'status' => 409
                ];
            }

            $ef = $payroll->real_salary - $payroll->accounting_salary;
            $isFirstPayment = $payroll->biweeklyPayments->isEmpty();
            $employee->load('employeeAffiliations');
            $aff = $employee->employeeAffiliations->sum(function ($em) use ($payroll) {
                return ($em->percent / 100) *  $payroll->real_salary;
            });

            $biweeklypayment = new BiweeklyPayment();
            $biweeklypayment->pay_roll_id = $payroll->id;
            $biweeklypayment->biweekly_date = now();
            $biweeklypayment->biweekly = $isFirstPayment ? 1 : 2;
            $biweeklypayment->real_amount = $isFirstPayment ? $ef * 0.4 : $ef * 0.6;

            if ($isFirstPayment) {
                $biweeklypayment->accounting_amount = $payroll->accounting_salary * 0.4;
                $biweeklypayment->discounts = 0;
                $biweeklypayment->additionals = 0;
            } else {
                $ds = $payroll->discountPayments->sum('amount');
                $as = $payroll->additionalPayments->sum('amount');
                $biweeklypayment->discounts = $ds + $aff;
                $biweeklypayment->additionals = $as;
                $biweeklypayment->accounting_amount = ($payroll->accounting_salary * 0.6) - $ds + $as - $aff;
            }

            $biweeklypayment->save();

            return [
                'message' => 'Biweekly payment created successfully',
                'status' => 201
            ];
        } catch (\Throwable $th) {
            return ['message' => 'Error, data cannot be processed' . $th, 'status' => 500];
        }
    }

    public function edit($key, $data)
    {
        $biweeklypayment = BiweeklyPayment::findOrFail($key);
        return BiweeklyPaymentData::from($biweeklypayment->update($data));
    }

    public function findBy($key)
    {
        return BiweeklyPaymentData::optional(BiweeklyPayment::where('dni', '=', $key)->first());
    }

    public function findAll()
    {
        return BiweeklyPaymentData::collect(BiweeklyPayment::where('status_code', '=', 'active')->get());
    }

    public function delete($key)
    {
        $biweeklypayment = BiweeklyPayment::findOrFail($key);
        $biweeklypayment->status_code = "deleted";
        return BiweeklyPaymentData::from($biweeklypayment->save());
    }

    public function createForAllEmployees()
{
    try {
        $currentYear = now()->year;
        $currentMonth = now()->month;
        $currentDay = now()->day;
        $biweekly = $currentDay <= 14 ? 1 : 2;

        $employees = Employee::with([
            'payrolls' => function ($query) use ($currentYear, $currentMonth) {
                $query->whereYear('created_at', $currentYear)
                    ->whereMonth('created_at', $currentMonth);
            },
            'employeeAffiliations'
        ])->get();

        $results = [];

        foreach ($employees as $employee) {
            try {
                $payroll = $employee->payrolls->first();

                if (!$payroll) {
                    $results[] = [
                        'employee' => $employee->dni,
                        'message' => 'No payroll found for the employee this month',
                        'status' => 404
                    ];
                    continue;
                }

                if ($payroll->biweeklyPayments->where('biweekly', $biweekly)->isNotEmpty()) {
                    $results[] = [
                        'employee' => $employee->dni,
                        'message' => "Biweekly payment for biweekly {$biweekly} already exists",
                        'status' => 409
                    ];
                    continue;
                }

                $ef = $payroll->real_salary - $payroll->accounting_salary;
                $aff = $employee->employeeAffiliations->sum(fn($em) => ($em->percent / 100) *  $payroll->accounting_salary);

                $biweeklypayment = new BiweeklyPayment();
                $biweeklypayment->pay_roll_id = $payroll->id;
                $biweeklypayment->biweekly_date = now();
                $biweeklypayment->biweekly = $biweekly;

                $loan = Loan::where('employee', $employee->dni)->first();
                $campaign = $payroll->campaign;

                // Cálculo de descuentos y adicionales
                $dis1 = $payroll->discountPayments
                    ->where('biweek', $biweekly)
                    ->where('pay_card', 1)
                    ->sum(fn($d) => $d->amount * $d->quantity);

                $dis2 = $payroll->discountPayments
                    ->where('biweek', $biweekly)
                    ->where('pay_card', 2)
                    ->sum(fn($d) => $d->amount * $d->quantity);

                $add1 = $payroll->additionalPayments
                    ->where('biweek', $biweekly)
                    ->where('pay_card', 1)
                    ->sum(fn($a) => $a->amount * $a->quantity);

                $add2 = $payroll->additionalPayments
                    ->where('biweek', $biweekly)
                    ->where('pay_card', 2)
                    ->sum(fn($a) => $a->amount * $a->quantity);

                $campaignAmount = 0;

                if ($campaign && $campaign->biweek == $biweekly) {
                    $campaignAmount = $campaign->amount;
                }

                if ($biweekly == 1) {
                    $biweeklypayment->discounts = $dis1 + $dis2;
                    $biweeklypayment->additionals = $add1 + $add2 + $campaignAmount;
                    $biweeklypayment->accounting_amount = $payroll->accounting_salary * 0.4 - $dis1 + $add1;
                    $biweeklypayment->real_amount = $ef * 0.4 - $dis2 + $add2;
                } else {
                    $biweeklypayment->discounts = $dis1 + $dis2 + $aff;
                    $biweeklypayment->additionals = $add1 + $add2 + $campaignAmount;
                    $biweeklypayment->accounting_amount = ($payroll->accounting_salary * 0.6) - $dis1 + $add1 - $aff;
                    $biweeklypayment->real_amount = $ef * 0.6 - $dis2 + $add2;
                }

                if ($loan) {
                    if ($loan->biweek === $biweekly) {
                        if ($loan->pay_card === 1) {
                            $biweeklypayment->accounting_amount -= $loan->amount;
                        } else {
                            $biweeklypayment->real_amount -= $loan->amount;
                        }
                        $biweeklypayment->discounts += $loan->amount;
                    }
                }

                $biweeklypayment->save();

                $results[] = [
                    'employee' => $employee->dni,
                    'message' => "Biweekly payment for biweekly {$biweekly} created successfully",
                    'status' => 201
                ];
            } catch (\Throwable $th) {
                $results[] = [
                    'employee' => $employee->dni,
                    'message' => 'Error processing employee: ' . $th->getMessage(),
                    'status' => 500
                ];
            }
        }

        return $results;
    } catch (\Throwable $th) {
        return ['message' => 'Critical error: ' . $th->getMessage(), 'status' => 500];
    }
}




    public function reportByBiweekly($request)
    {
        try {
            $date = $request->input('pay_date');
            $biweek = $request->input('biweek');

            $year = date('Y', strtotime($date));
            $month = date('m', strtotime($date));

            $headquarters = Headquarter::with([
                'employees.payRolls.biweeklyPayments' => function ($query) use ($biweek, $year, $month) {
                    $query->where('biweekly', $biweek)
                        ->whereYear('biweekly_date', $year)
                        ->whereMonth('biweekly_date', $month);
                },
                'employees.payRolls.campaign',
            ])->get();

            $result = $headquarters->map(function ($headquarter) use ($biweek) {
                return [
                    'headquarter' => $headquarter->name,
                    'employees' => $headquarter->employees->map(function ($employee) use ($biweek) {
                        $payrollsWithPayments = $employee->payRolls->filter(fn($payroll) => $payroll->biweeklyPayments->isNotEmpty());
                        $amount = $employee->payRolls->sum(fn($payroll) => $payroll->loan ? $payroll->loan->amount : 0);
                        return [
                            'name' => $employee->firstname . ' ' . $employee->lastname,
                            'dni' => $employee->dni,
                            'amount_loan' => $amount,
                            'pay_date' => optional($payrollsWithPayments->first())->created_at ?? null,
                            'biweeklyPayments' => $payrollsWithPayments->flatMap->biweeklyPayments->map(function ($payment) {
                                return [
                                    'biweekly' => $payment->biweekly,
                                    'biweekly_date' => $payment->biweekly_date,
                                    'accounting_amount' => $payment->accounting_amount,
                                    'real_amount' => $payment->real_amount,
                                ];
                            }),
                            'campaign' => optional($payrollsWithPayments->first())->campaign
                                ? ($payrollsWithPayments->first()->campaign->biweek === $biweek
                                    ? [
                                        'description' => $payrollsWithPayments->first()->campaign->description,
                                        'amount' => $payrollsWithPayments->first()->campaign->amount,
                                    ]
                                    : null)
                                : null,

                        ];
                    })->filter(fn($employee) => $employee['biweeklyPayments']->isNotEmpty()),
                ];
            })->filter(fn($hq) => $hq['employees']->isNotEmpty());

            return response()->json(['data' => $result], 200);
        } catch (\Exception $th) {
            return response()->json(['error' => 'Failed to fetch payrolls', 'message' => $th->getMessage()], 500);
        }
    }
}
