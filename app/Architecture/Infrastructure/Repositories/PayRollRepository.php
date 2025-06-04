<?php

namespace App\Architecture\Infrastructure\Repositories;

use App\Architecture\Application\Dto\ResponseListPayRoll;
use App\Architecture\Domain\Models\Entities\AffiliationData;
use App\Architecture\Domain\Models\Entities\EmployeeData;
use App\Architecture\Domain\Models\Entities\PayRollData;
use App\Exceptions\EntityNotFoundException;
use App\Models\AdditionalPayment;
use App\Models\DiscountPayment;
use App\Models\Employee;
use App\Models\Extra;
use App\Models\Headquarter;
use App\Models\Loan;
use App\Models\PaymentType;
use App\Models\PayRoll;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PayRollRepository
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function create($request)
    {
        try {
            $employee = Employee::where('dni', $request['employee'])
                ->firstOrFail();

            if (!$employee) {
                return ['message' => 'Employee not found', 'status' => 404];
            }
            $currentYear = now()->year;
            $currentMonth = now()->month;

            $existingPayroll = Payroll::where('employee_id', $employee->id)
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $currentMonth)
                ->first();

            if ($existingPayroll) {
                return ['message' => 'The employee already has a payroll for the given month and year', 'status' => 409];
            }

            $aux = $employee->activeContract();
            $payroll = Payroll::create([
                'accounting_salary' => $aux->accounting_salary,
                'real_salary' => $aux->real_salary,
                'employee_id' => $employee->id,
            ]);

            foreach ($request['additionalPayments'] as $add) {
                AdditionalPayment::create([
                    'pay_roll_id' => $payroll->id,
                    'payment_type_id' => $add['id'],
                    'amount' => $add['amount'],
                    'quantity' => $add['quantity'] ?? 1,
                ]);
            }

            foreach ($request['discountPayments'] as $add) {
                DiscountPayment::create([
                    'pay_roll_id' => $payroll->id,
                    'discount_type_id' => $add['id'],
                    'amount' => $add['amount'],
                    'quantity' => $add['quantity'] ?? 1,
                ]);
            }

            return ['message' => 'Payroll created successfully', 'status' => 201];
        } catch (\Throwable $th) {
            return ['message' => 'Error, data cannot be processed' . $th, 'status' => 500];
        }
    }

    public function edit($key, $request)
    {
        try {
            $employee = Employee::where('dni', $key)->first();
            if (!$employee) {
                return ['message' => 'Employee not found', 'status' => 404];
            }
            $currentYear = now()->year;
            $currentMonth = now()->month;
            $payroll = Payroll::where('employee_id', $employee->id)
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $currentMonth)
                ->first();

            if (!$payroll) {
                return ['message' => 'PayRoll not found', 'status' => 404];
            }
            AdditionalPayment::where('pay_roll_id', $payroll->id)->delete();
            DiscountPayment::where('pay_roll_id', $payroll->id)->delete();
            foreach ($request['additionalPayments'] as $add) {
                AdditionalPayment::create([
                    'pay_roll_id' => $payroll->id,
                    'payment_type_id' => $add['id'],
                    'amount' => $add['amount'],
                    'quantity' => $add['quantity'] ?? 1,
                    'biweek' => $add['biweek'] ?? 2,
                    'pay_card' => $add['pay_card'] ?? 1,
                ]);
            }

            foreach ($request['discountPayments'] as $add) {
                DiscountPayment::create([
                    'pay_roll_id' => $payroll->id,
                    'discount_type_id' => $add['id'],
                    'amount' => $add['amount'],
                    'quantity' => $add['quantity'] ?? 1,
                    'biweek' => $add['biweek'] ?? 2,
                    'pay_card' => $add['pay_card'] ?? 1,
                ]);
            }
            return ['message' => 'PayRoll edited successfully', 'status' => 201];
        } catch (\Throwable $th) {
            return ['message' => $th->getMessage(), 'status' => 500];
        }
    }

    public function findBy($key)
    {
        try {
            $employee = Employee::where('dni', $key)->first();

            if (!$employee) {
                throw new EntityNotFoundException('Employee not found');
            }

            $currentYear = now()->year;
            $currentMonth = now()->month;

            $payroll = PayRoll::with(['employee', 'additionalPayments', 'discountPayments', 'biweeklyPayments'])
                ->where('employee_id', $employee->id)
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $currentMonth)
                ->first();

            if (!$payroll) {
                throw new EntityNotFoundException('Payroll not found for the given month and year');
            }

            $data = [
                'employee' => $payroll->employee->dni,
                'pay_date' => $payroll->created_at->format('Y-m'),
                'accounting_salary' => $payroll->accounting_salary,
                'real_salary' => $payroll->real_salary,
                'additionalPayments' => $payroll->additionalPayments->map(fn($payment) => [
                    'id' => $payment->paymentType->id,
                    'description' => $payment->paymentType->description,
                    'amount' => $payment->amount,
                    'quantity' => $payment->quantity,
                    'biweek' => $payment->biweek,
                    'pay_card' => $payment->pay_card
                ])->toArray(),
                'discountPayments' => $payroll->discountPayments->map(fn($discount) => [
                    'id' => $discount->discountType->id,
                    'description' => $discount->discountType->description,
                    'amount' => $discount->amount,
                    'quantity' => $discount->quantity,
                    'biweek' => $discount->biweek,
                    'pay_card' => $discount->pay_card
                ])->toArray(),
                'biweeklyPayments' => $payroll->biweeklyPayments->map(fn($biweekly) => [
                    'biweekly' => $biweekly->biweekly,
                    'biweekly_date' => $biweekly->biweekly_date,
                    'real_amount' => $biweekly->real_amount,
                    'accounting_amount' => $biweekly->accounting_amount,
                    'discounts' => $biweekly->discounts,
                    'additionals' => $biweekly->additionals,
                ])->toArray(),
            ];

            return [
                'message' => 'Payroll data retrieved successfully',
                'data' => PayRollData::optional($data),
                'status' => 200
            ];
        } catch (EntityNotFoundException $e) {
            return [
                'message' => $e->getMessage(),
                'status' => 404
            ];
        } catch (\Throwable $th) {
            return [
                'message' => 'Error retrieving payroll data: ' . $th->getMessage(),
                'status' => 500
            ];
        }
    }


    public function findAll($request)
    {
        try {
            $year = $request['year'] ?? now()->year;
            $month = $request['month'] ?? now()->month;

            $payrolls = PayRoll::with([
                'employee' => function ($query) {
                    $query->withTrashed()->select('id', 'dni', 'firstname', 'lastname', 'headquarter_id');
                },
                'employee.headquarter' => function ($query) {
                    $query->select('id', 'name');
                }
            ])
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->get();

            return ResponseListPayRoll::collect(
                $payrolls->map(function ($payroll) {
                    $emp = $payroll->employee;
                    return [
                        'name' => $emp->firstname . ' ' . $emp->lastname,
                        'dni' => $emp->dni,
                        'headquarter' => $emp->headquarter ? $emp->headquarter->name : 'N/A',
                        'pay_date' => $payroll->created_at->format('Y-m'),
                        'accounting_salary' => $payroll->accounting_salary,
                        'real_salary' => $payroll->real_salary,
                    ];
                })
            );
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error retrieving payrolls: ' . $th->getMessage(),
                'status' => 500
            ], 500);
        }
    }


    public function delete($key)
    {
        $payroll = PayRoll::findOrFail($key);
        $payroll->status_code = "deleted";
        return PayRollData::from($payroll->save());
    }

    public function findByEmployeeAndPaydate($dni, $pay_date)
    {
        try {
            $employee = Employee::where('dni', $dni)->first();

            if (!$employee) {
                throw new EntityNotFoundException('Employee not found');
            }

            $payroll = PayRoll::with(['employee', 'additionalPayments', 'discountPayments', 'biweeklyPayments'])
                ->where('employee_id', $employee->id)
                ->whereYear('created_at', Carbon::parse($pay_date)->year)
                ->whereMonth('created_at', Carbon::parse($pay_date)->month)
                ->firstOrFail();

            $affiliations = $payroll->employee->employeeAffiliations->map(function ($employeeAffiliation) {
                return AffiliationData::from(
                    [
                        'description' => $employeeAffiliation->affiliation->description,
                        'percent' => $employeeAffiliation->percent,
                    ]
                );
            });
            $payroll->employee->load('headquarter');
            $employeeData = EmployeeData::optional($payroll->employee)->toArray();
            $employeeData['affiliations'] = $affiliations;
            $loan = $payroll->loan ? $payroll->loan->amount : 0;
            $campaign = $payroll->campaign ?? null;
            $data = [
                'employee' => $employeeData,
                'pay_date' => Carbon::parse($payroll->created_at)->format('Y-m'),
                'accounting_salary' => $payroll->accounting_salary,
                'real_salary' => $payroll->real_salary,
                'additionalPayments' => $payroll->additionalPayments->map(fn($payment) => [
                    'description' => $payment->paymentType->description,
                    'amount' => $payment->amount,
                    'quantity' => $payment->quantity,
                    'biweek' => $payment->biweek
                ])->toArray(),
                'discountPayments' => $payroll->discountPayments->map(fn($discount) => [
                    'description' => $discount->discountType->description,
                    'amount' => $discount->amount,
                    'quantity' => $discount->quantity,
                    'biweek' => $discount->biweek
                ])->toArray(),
                'biweeklyPayments' => $payroll->biweeklyPayments->map(fn($biweekly) => [
                    'biweekly' => $biweekly->biweekly,
                    'biweekly_date' => $biweekly->biweekly_date,
                    'real_amount' => $biweekly->real_amount,
                    'accounting_amount' => $biweekly->accounting_amount,
                    'discounts' => $biweekly->discounts,
                    'additionals' => $biweekly->additionals,
                ])->toArray(),
                'loan' => $loan,
                'campaign' => $campaign
            ];

            return PayRollData::optional($data);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Payroll not found'], 404);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }


    public function generatePayRolls($headquarter = 'all', $pay_date)
    {
        try {
            if (!$pay_date) {
                throw new \InvalidArgumentException('pay_date is required');
            }

            $query = PayRoll::with([
                'employee.headquarter',
                'employee.employeeAffiliations.affiliation',
                'additionalPayments.paymentType',
                'discountPayments.discountType',
                'biweeklyPayments',
                'campaign'
            ])
                ->whereYear('created_at', date('Y', strtotime($pay_date)))
                ->whereMonth('created_at', date('m', strtotime($pay_date)));

            if ($headquarter !== 'all') {
                $head = Headquarter::where('name', $headquarter)->first();
                if (!$head) {
                    return response()->json(['error' => 'Headquarter not found'], 404);
                }
                $query->whereHas('employee', function ($q) use ($head) {
                    $q->where('headquarter_id', $head->id);
                });
            }

            $payrolls = $query->get();

            $groupedByHeadquarter = $payrolls->groupBy(fn($payroll) => $payroll->employee->headquarter->name ?? 'Sin Sede');

            $result = $groupedByHeadquarter->map(function ($payrolls, $headquarterName) {
                return [
                    'headquarter' => $headquarterName,
                    'employees' => $payrolls->map(function ($payroll) {
                        return [
                            'employee' => [
                                'name' => $payroll->employee->firstname . ' ' . $payroll->employee->lastname,
                                'dni' => $payroll->employee->dni,
                                'affiliations' => $payroll->employee->employeeAffiliations->map(fn($affiliation) => [
                                    'description' => $affiliation->affiliation->description,
                                    'percent' => $affiliation->percent,
                                ])->toArray(),
                            ],
                            'pay_date' => $payroll->pay_date,
                            'accounting_salary' => $payroll->accounting_salary,
                            'real_salary' => $payroll->real_salary,
                            'additionalPayments' => $payroll->additionalPayments->map(fn($payment) => [
                                'description' => $payment->paymentType->description,
                                'amount' => $payment->amount,
                                'quantity' => $payment->quantity,
                            ])->toArray(),
                            'discountPayments' => $payroll->discountPayments->map(fn($discount) => [
                                'description' => $discount->discountType->description,
                                'amount' => $discount->amount,
                                'quantity' => $discount->quantity,
                            ])->toArray(),
                            'biweeklyPayments' => $payroll->biweeklyPayments->map(fn($biweekly) => [
                                'biweekly' => $biweekly->biweekly,
                                'biweekly_date' => $biweekly->biweekly_date,
                                'real_amount' => $biweekly->real_amount,
                                'accounting_amount' => $biweekly->accounting_amount,
                                'discounts' => $biweekly->discounts,
                                'additionals' => $biweekly->additionals,
                            ])->toArray(),
                            'campaign' => $payroll->campaign ? [
                                'description' => $payroll->campaign->description,
                                'amount' => $payroll->campaign->amount
                            ] : null,
                        ];
                    })->values(),
                ];
            })->values();

            return response()->json($result, 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'Invalid input', 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch payrolls', 'message' => $e->getMessage()], 500);
        }
    }

    public function createForAllEmployees()
    {
        try {
            $currentYear = now()->year;
            $currentMonth = now()->month;

            $employees = Employee::all();

            $payrollsCreated = 0;
            $payrollsSkipped = 0;

            foreach ($employees as $employee) {
                $existingPayroll = Payroll::where('employee_id', $employee->id)
                    ->whereYear('created_at', $currentYear)
                    ->whereMonth('created_at', $currentMonth)
                    ->first();

                if ($existingPayroll) {
                    $payrollsSkipped++;
                    continue;
                }

                $aux = $employee->activeContract();
                if (!$aux) {
                    $payrollsSkipped++;
                    continue;
                }

                $loan = Loan::where('employee', $employee->dni)
                    ->whereDate('start_date', '<=', now())
                    ->whereDate('end_date', '>=', now())
                    ->first();

                $payroll = Payroll::create([
                    'accounting_salary' => $aux->accounting_salary,
                    'real_salary' => $aux->real_salary,
                    'employee_id' => $employee->id,
                    'loan_id' => $loan ? $loan->id : null
                ]);

                $payrollsCreated++;

                $extras = Extra::where('employee', $payroll->employee->dni)
                    ->whereYear('apply_date', now()->year)
                    ->whereMonth('apply_date', now()->month)
                    ->get();

                if ($extras->isEmpty()) {
                    continue;
                }
                $paymentTypes = PaymentType::whereIn('description', $extras->pluck('description'))->get();

                foreach ($extras as $extra) {
                    $paymentType = $paymentTypes->firstWhere('description', $extra->description);

                    if (!$paymentType) {
                        continue;
                    }

                    AdditionalPayment::create([
                        'pay_roll_id' => $payroll->id,
                        'payment_type_id' => $paymentType->id,
                        'amount' => $extra->amount,
                        'quantity' => $extra->quantity ?? 1,
                        'biweek' => 1,
                    ]);
                }

                Extra::where('employee', $employee->dni)
                    ->whereYear('apply_date', now()->year)
                    ->whereMonth('apply_date', now()->month)
                    ->delete();
            }

            return [
                'message' => "Payrolls created successfully: $payrollsCreated, Skipped: $payrollsSkipped",
                'status' => 201
            ];
        } catch (\Throwable $th) {
            return ['message' => 'Error processing payrolls: ' . $th->getMessage(), 'status' => 500];
        }
    }

    public function createPayrollsForSpecificEmployees($request)
    {
        try {
            $currentYear = now()->year;
            $currentMonth = now()->month;
            $dniList = $request->input('dni_list', []);

            if (empty($dniList)) {
                return ['message' => 'No DNIs provided', 'status' => 400];
            }

            $employees = Employee::whereIn('dni', $dniList)->get();

            if ($employees->isEmpty()) {
                return ['message' => 'No employees found for provided DNIs', 'status' => 404];
            }

            $payrollsCreated = 0;
            $payrollsSkipped = 0;

            foreach ($employees as $employee) {
                $existingPayroll = Payroll::where('employee_id', $employee->id)
                    ->whereYear('created_at', $currentYear)
                    ->whereMonth('created_at', $currentMonth)
                    ->first();

                if ($existingPayroll) {
                    $payrollsSkipped++;
                    continue;
                }

                $aux = $employee->activeContract();
                if (!$aux) {
                    $payrollsSkipped++;
                    continue;
                }

                $payroll = Payroll::create([
                    'accounting_salary' => $aux->accounting_salary,
                    'real_salary' => $aux->real_salary,
                    'employee_id' => $employee->id,
                ]);

                $payrollsCreated++;

                $extras = Extra::where('employee', $payroll->employee->dni)
                    ->whereYear('apply_date', now()->year)
                    ->whereMonth('apply_date', now()->month)
                    ->get();

                if ($extras->isNotEmpty()) {
                    $paymentTypes = PaymentType::whereIn('description', $extras->pluck('description'))->get();

                    foreach ($extras as $extra) {
                        $paymentType = $paymentTypes->firstWhere('description', $extra->description);
                        if (!$paymentType) continue;

                        AdditionalPayment::create([
                            'pay_roll_id' => $payroll->id,
                            'payment_type_id' => $paymentType->id,
                            'amount' => $extra->amount,
                            'quantity' => $extra->quantity ?? 1,
                            'biweek' => 2,
                        ]);
                    }

                    Extra::where('employee', $employee->dni)
                        ->whereYear('apply_date', now()->year)
                        ->whereMonth('apply_date', now()->month)
                        ->delete();
                }
            }

            return [
                'message' => "Payrolls created successfully: $payrollsCreated, Skipped: $payrollsSkipped",
                'status' => 201
            ];
        } catch (\Throwable $th) {
            return ['message' => 'Error processing payrolls: ' . $th->getMessage(), 'status' => 500];
        }
    }
}
