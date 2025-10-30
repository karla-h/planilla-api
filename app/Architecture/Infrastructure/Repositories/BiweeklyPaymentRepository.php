<?php

namespace App\Architecture\Infrastructure\Repositories;

use App\Architecture\Application\Services\PayrollCalculatorService;
use App\Models\BiweeklyPayment;
use App\Models\Employee;
use App\Models\Headquarter;
use App\Models\Loan;
use App\Models\PayRoll;
use Carbon\Carbon;

class BiweeklyPaymentRepository
{
    protected $calculator;

    public function __construct()
    {
        $this->calculator = new PayrollCalculatorService();
    }

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

    public function createForAllEmployees()
    {
        try {
            $currentYear = now()->year;
            $currentMonth = now()->month;
            $currentDay = now()->day;
            
            $employees = Employee::with([
                'activeContract',
                'payrolls' => function ($query) use ($currentYear, $currentMonth) {
                    $query->whereYear('created_at', $currentYear)
                        ->whereMonth('created_at', $currentMonth);
                },
                'employeeAffiliations'
            ])->get();

            $results = [];

            foreach ($employees as $employee) {
                try {
                    if (!$employee->activeContract) {
                        $results[] = [
                            'employee' => $employee->dni,
                            'message' => 'No tiene contrato activo',
                            'status' => 404
                        ];
                        continue;
                    }

                    $contract = $employee->activeContract;
                    $payroll = $employee->payrolls->first();

                    if (!$payroll) {
                        $results[] = [
                            'employee' => $employee->dni,
                            'message' => 'No tiene planilla este mes',
                            'status' => 404
                        ];
                        continue;
                    }

                    // Solo procesar empleados con pago quincenal
                    if ($contract->payment_type !== 'quincenal') {
                        $results[] = [
                            'employee' => $employee->dni,
                            'message' => 'Contrato mensual - no aplica pago quincenal',
                            'status' => 200
                        ];
                        continue;
                    }

                    $biweekly = $currentDay <= 15 ? 1 : 2;

                    if ($payroll->biweeklyPayments->where('biweekly', $biweekly)->isNotEmpty()) {
                        $results[] = [
                            'employee' => $employee->dni,
                            'message' => "Pago quincenal {$biweekly} ya existe",
                            'status' => 409
                        ];
                        continue;
                    }

                    $calculator = new PayrollCalculatorService();
                    $periods = $calculator->getPaymentPeriods('quincenal', $currentYear, $currentMonth);
                    $currentPeriod = $periods[$biweekly - 1];

                    // Calcular días trabajados proporcionales
                    $workedDays = $calculator->calculateWorkedDays(
                        $contract->hire_date,
                        $currentPeriod['start'],
                        $currentPeriod['end'],
                        true
                    );

                    // Resto de la lógica de cálculo...
                    $ef = $payroll->real_salary - $payroll->accounting_salary;
                    $aff = $employee->employeeAffiliations->sum(fn($em) => ($em->percent / 100) * $payroll->accounting_salary);

                    $biweeklypayment = new BiweeklyPayment();
                    $biweeklypayment->pay_roll_id = $payroll->id;
                    $biweeklypayment->biweekly_date = now();
                    $biweeklypayment->biweekly = $biweekly;
                    $biweeklypayment->worked_days = $workedDays; // Nuevo campo

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

                    $campaign = $payroll->campaign;
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

                    $loan = Loan::where('employee', $employee->dni)->first();
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
                        'message' => "Pago quincenal {$biweekly} creado exitosamente ({$workedDays} días trabajados)",
                        'status' => 201
                    ];

                } catch (\Throwable $th) {
                    $results[] = [
                        'employee' => $employee->dni,
                        'message' => 'Error: ' . $th->getMessage(),
                        'status' => 500
                    ];
                }
            }

            return $results;
        } catch (\Throwable $th) {
            return ['message' => 'Error crítico: ' . $th->getMessage(), 'status' => 500];
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