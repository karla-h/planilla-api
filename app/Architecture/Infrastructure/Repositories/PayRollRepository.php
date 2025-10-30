<?php

namespace App\Architecture\Infrastructure\Repositories;

use App\Architecture\Application\Dto\ResponseListPayRoll as DtoResponseListPayRoll;
use App\Architecture\Application\Services\PayrollCalculatorService;
use App\Architecture\Domain\Models\Dto\ResponseListPayRoll;
use App\Architecture\Domain\Models\Entities\AffiliationData;
use App\Architecture\Domain\Models\Entities\EmployeeData;
use App\Architecture\Domain\Models\Entities\PayRollData;
use App\Exceptions\EntityNotFoundException;
use App\Models\AdditionalPayment;
use App\Models\Contract;
use App\Models\DiscountPayment;
use App\Models\Employee;
use App\Models\Extra;
use App\Models\Headquarter;
use App\Models\Loan;
use App\Models\PaymentType;
use App\Models\PayRoll;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class PayRollRepository
{
    protected $calculator;

    public function __construct()
    {
        $this->calculator = new PayrollCalculatorService();
    }

    public function create($request)
{
    try {
        Log::info('PayRollRepository@create - Iniciando', ['request' => $request]);
        
        $employee = Employee::where('dni', $request['employee'])->first();

        if (!$employee) {
            return [
                'message' => 'Employee not found', 
                'status' => 404
            ];
        }

        $currentYear = now()->year;
        $currentMonth = now()->month;

        $existingPayroll = PayRoll::where('employee_id', $employee->id)
            ->whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $currentMonth)
            ->first();

        if ($existingPayroll) {
            return [
                'message' => 'The employee already has a payroll for the given month and year', 
                'status' => 409
            ];
        }

        // VERIFICAR CONTRATO ACTIVO DE FORMA EXPLÍCITA
        $activeContract = Contract::where('employee_id', $employee->id)
            ->where('status_code', 'active')
            ->first();

        Log::info('Contrato activo buscado', [
            'employee_id' => $employee->id,
            'contract_found' => $activeContract ? $activeContract->id : 'null'
        ]);

        if (!$activeContract) {
            return [
                'message' => 'Employee does not have an active contract',
                'status' => 400
            ];
        }

        // Crear la planilla con periodo
        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        $payroll = PayRoll::create([
            'accounting_salary' => $activeContract->accounting_salary,
            'real_salary' => $activeContract->real_salary,
            'employee_id' => $employee->id,
            'status' => PayRoll::STATUS_OPEN,
            'period_start' => $periodStart,
            'period_end' => $periodEnd
        ]);

        Log::info('Planilla creada exitosamente', ['payroll_id' => $payroll->id]);

        // Crear additional payments si existen
        if (isset($request['additionalPayments']) && is_array($request['additionalPayments'])) {
            foreach ($request['additionalPayments'] as $add) {
                AdditionalPayment::create([
                    'pay_roll_id' => $payroll->id,
                    'payment_type_id' => $add['payment_type_id'],
                    'amount' => $add['amount'],
                    'quantity' => $add['quantity'] ?? 1,
                    'biweek' => $add['biweek'] ?? 1,
                    'pay_card' => $add['pay_card'] ?? 1,
                ]);
            }
        }

        // Crear discount payments si existen
        if (isset($request['discountPayments']) && is_array($request['discountPayments'])) {
            foreach ($request['discountPayments'] as $disc) {
                DiscountPayment::create([
                    'pay_roll_id' => $payroll->id,
                    'discount_type_id' => $disc['discount_type_id'],
                    'amount' => $disc['amount'],
                    'quantity' => $disc['quantity'] ?? 1,
                    'biweek' => $disc['biweek'] ?? 2,
                    'pay_card' => $disc['pay_card'] ?? 1,
                ]);
            }
        }

        return [
            'message' => 'Payroll created successfully',
            'status' => 201,
            'data' => [
                'payroll_id' => $payroll->id,
                'employee' => $employee->dni,
                'accounting_salary' => $payroll->accounting_salary,
                'real_salary' => $payroll->real_salary,
                'status' => $payroll->status
            ]
        ];

    } catch (\Throwable $th) {
        Log::error('Error en PayRollRepository@create', [
            'message' => $th->getMessage(),
            'trace' => $th->getTraceAsString()
        ]);
        
        return [
            'message' => 'Error, data cannot be processed: ' . $th->getMessage(), 
            'status' => 500
        ];
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

            return DtoResponseListPayRoll::collect(
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

    /**
     * Calcular pago según tipo de contrato con días proporcionales
     */
    public function calculatePayment($employeeId, $year, $month, $periodType = null)
    {
        try {
            $employee = Employee::with(['activeContract', 'employeeAffiliations.affiliation'])->find($employeeId);

            if (!$employee || !$employee->activeContract) {
                return [
                    'status' => 404,
                    'message' => 'Empleado o contrato activo no encontrado'
                ];
            }

            $contract = $employee->activeContract;

            // Obtener periodos según tipo de pago
            $periods = $this->calculator->getPaymentPeriods($contract->payment_type, $year, $month);

            $results = [];

            foreach ($periods as $period) {
                // Si se especifica un periodo específico, filtrar
                if ($periodType && $period['type'] !== $periodType) {
                    continue;
                }

                // Obtener pagos adicionales y descuentos para este periodo
                $additionalPayments = AdditionalPayment::whereHas('payRoll', function ($q) use ($employeeId, $year, $month) {
                    $q->where('employee_id', $employeeId)
                        ->whereYear('created_at', $year)
                        ->whereMonth('created_at', $month);
                })->get();

                $discountPayments = DiscountPayment::whereHas('payRoll', function ($q) use ($employeeId, $year, $month) {
                    $q->where('employee_id', $employeeId)
                        ->whereYear('created_at', $year)
                        ->whereMonth('created_at', $month);
                })->get();

                // Calcular afiliaciones
                $affiliationDiscounts = $employee->employeeAffiliations->sum(function ($aff) use ($contract) {
                    return ($aff->percent / 100) * $contract->real_salary;
                });

                // Calcular pagos
                $paymentCalculation = $this->calculator->calculatePayments(
                    $contract,
                    $period,
                    $additionalPayments->toArray(),
                    $discountPayments->toArray()
                );

                if ($paymentCalculation) {
                    // Aplicar descuentos de afiliaciones
                    if ($contract->payment_type === 'quincenal' && $period['type'] === 'quincena_2') {
                        $paymentCalculation['bank_transfer'] -= $affiliationDiscounts;
                        $paymentCalculation['discounts'] += $affiliationDiscounts;
                    } elseif ($contract->payment_type === 'mensual') {
                        $paymentCalculation['bank_transfer'] -= $affiliationDiscounts;
                        $paymentCalculation['discounts'] += $affiliationDiscounts;
                    }

                    $results[] = array_merge($period, $paymentCalculation, [
                        'employee' => [
                            'id' => $employee->id,
                            'name' => $employee->firstname . ' ' . $employee->lastname,
                            'dni' => $employee->dni
                        ],
                        'contract_type' => $contract->payment_type,
                        'affiliation_discounts' => $affiliationDiscounts,
                        'base_accounting_salary' => $contract->accounting_salary,
                        'base_real_salary' => $contract->real_salary
                    ]);
                }
            }

            return [
                'status' => 200,
                'data' => $results
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error calculando pagos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generar pagos quincenales con cálculos proporcionales
     */
    public function generateProportionalBiweeklyPayments($employeeId, $year, $month, $biweekly)
    {
        try {
            $employee = Employee::with(['activeContract', 'employeeAffiliations'])->find($employeeId);

            if (!$employee || !$employee->activeContract) {
                return [
                    'status' => 404,
                    'message' => 'Empleado o contrato activo no encontrado'
                ];
            }

            $contract = $employee->activeContract;

            // Solo para contratos quincenales
            if ($contract->payment_type !== 'quincenal') {
                return [
                    'status' => 400,
                    'message' => 'Solo aplica para contratos quincenales'
                ];
            }

            $payroll = PayRoll::where('employee_id', $employeeId)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->first();

            if (!$payroll) {
                return [
                    'status' => 404,
                    'message' => 'No se encontró planilla para este mes'
                ];
            }

            // Verificar si ya existe el pago quincenal
            $existingPayment = $payroll->biweeklyPayments()->where('biweekly', $biweekly)->first();
            if ($existingPayment) {
                return [
                    'status' => 409,
                    'message' => 'El pago quincenal ya existe'
                ];
            }

            $periods = $this->calculator->getPaymentPeriods('quincenal', $year, $month);
            $currentPeriod = $periods[$biweekly - 1];

            // Obtener adicionales y descuentos específicos del periodo
            $additionalPayments = $payroll->additionalPayments()
                ->where('biweek', $biweekly)
                ->get();

            $discountPayments = $payroll->discountPayments()
                ->where('biweek', $biweekly)
                ->get();

            // Calcular pagos
            $paymentCalculation = $this->calculator->calculatePayments(
                $contract,
                $currentPeriod,
                $additionalPayments->toArray(),
                $discountPayments->toArray()
            );

            if (!$paymentCalculation) {
                return [
                    'status' => 500,
                    'message' => 'Error en el cálculo de pagos'
                ];
            }

            // Aplicar descuentos de afiliaciones en segunda quincena
            $affiliationDiscounts = $employee->employeeAffiliations->sum(function ($aff) use ($contract) {
                return ($aff->percent / 100) * $contract->real_salary;
            });

            if ($biweekly === 2) {
                $paymentCalculation['bank_transfer'] -= $affiliationDiscounts;
                $paymentCalculation['discounts'] += $affiliationDiscounts;
            }

            // Crear registro de pago quincenal
            $biweeklyPayment = $payroll->biweeklyPayments()->create([
                'biweekly' => $biweekly,
                'biweekly_date' => now(),
                'accounting_amount' => $paymentCalculation['bank_transfer'],
                'real_amount' => $paymentCalculation['cash'],
                'additions' => $paymentCalculation['additions'] ?? 0,
                'discounts' => $paymentCalculation['discounts'] ?? 0,
                'worked_days' => $paymentCalculation['worked_days']
            ]);

            return [
                'status' => 201,
                'message' => 'Pago quincenal generado exitosamente',
                'data' => array_merge($paymentCalculation, [
                    'biweekly' => $biweekly,
                    'period' => $currentPeriod,
                    'affiliation_discounts' => $biweekly === 2 ? $affiliationDiscounts : 0
                ])
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error generando pago quincenal: ' . $e->getMessage()
            ];
        }
    }

    // En PayRollRepository, agregar estos métodos:

    /**
     * Eliminar un pago quincenal
     */
    public function deleteBiweeklyPayment($payrollId, $biweeklyId)
    {
        try {
            $payroll = PayRoll::findOrFail($payrollId);

            if (!$payroll->canDeletePayments()) {
                return [
                    'status' => 403,
                    'message' => 'No tiene permisos para eliminar pagos en el estado actual'
                ];
            }

            $biweeklyPayment = $payroll->biweeklyPayments()->findOrFail($biweeklyId);
            $biweeklyPayment->delete();

            // Actualizar estado de la planilla si es necesario
            $completedPayments = $payroll->getCompletedBiweeklyPayments();
            $expectedPayments = $payroll->getExpectedBiweeklyPayments();

            $newStatus = match (true) {
                $completedPayments === 0 => PayRoll::STATUS_OPEN,
                $completedPayments < $expectedPayments => PayRoll::STATUS_PARTIAL,
                default => $payroll->status
            };

            if ($newStatus !== $payroll->status) {
                $payroll->update(['status' => $newStatus]);
            }

            return [
                'status' => 200,
                'message' => 'Pago eliminado exitosamente',
                'data' => [
                    'payroll_status' => $newStatus,
                    'remaining_payments' => $completedPayments
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error al eliminar pago: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Abrir una planilla
     */
    public function openPayroll($id)
    {
        try {
            $payroll = PayRoll::findOrFail($id);

            if (!$payroll->isDraft()) {
                return [
                    'status' => 400,
                    'message' => 'Solo se pueden abrir planillas en estado DRAFT'
                ];
            }

            $payroll->update(['status' => PayRoll::STATUS_OPEN]);

            return [
                'status' => 200,
                'message' => 'Planilla abierta exitosamente',
                'data' => $payroll
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error al abrir planilla: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cerrar una planilla
     */
    public function closePayroll($id)
    {
        try {
            $payroll = PayRoll::findOrFail($id);

            if (!$payroll->canClose()) {
                return [
                    'status' => 403,
                    'message' => 'No se puede cerrar la planilla en el estado actual'
                ];
            }

            $payroll->update(['status' => PayRoll::STATUS_CLOSED]);

            return [
                'status' => 200,
                'message' => 'Planilla cerrada exitosamente',
                'data' => $payroll
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error al cerrar planilla: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Bloquear una planilla para correcciones
     */
    public function lockPayroll($id)
    {
        try {
            $payroll = PayRoll::findOrFail($id);

            // Solo se puede bloquear si está en OPEN o PARTIAL
            if (!$payroll->isOpen() && !$payroll->isPartial()) {
                return [
                    'status' => 400,
                    'message' => 'Solo se pueden bloquear planillas en estado OPEN o PARTIAL'
                ];
            }

            $payroll->update(['status' => PayRoll::STATUS_LOCKED]);

            return [
                'status' => 200,
                'message' => 'Planilla bloqueada para correcciones',
                'data' => $payroll
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error al bloquear planilla: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Desbloquear una planilla
     */
    public function unlockPayroll($id)
    {
        try {
            $payroll = PayRoll::findOrFail($id);

            if (!$payroll->isLocked()) {
                return [
                    'status' => 400,
                    'message' => 'Solo se pueden desbloquear planillas en estado LOCKED'
                ];
            }

            // Determinar el estado apropiado al desbloquear
            $completedPayments = $payroll->getCompletedBiweeklyPayments();
            $expectedPayments = $payroll->getExpectedBiweeklyPayments();

            $newStatus = match (true) {
                $completedPayments === 0 => PayRoll::STATUS_OPEN,
                $completedPayments < $expectedPayments => PayRoll::STATUS_PARTIAL,
                default => PayRoll::STATUS_OPEN // Volver a OPEN para permitir más ediciones
            };

            $payroll->update(['status' => $newStatus]);

            return [
                'status' => 200,
                'message' => 'Planilla desbloqueada',
                'data' => $payroll
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error al desbloquear planilla: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Re-generar un pago quincenal existente
     */
    public function regenerateBiweeklyPayment($payrollId, $biweeklyId)
    {
        try {
            $payroll = PayRoll::with(['employee.activeContract', 'biweeklyPayments'])->findOrFail($payrollId);

            if (!$payroll->canEditPayments()) {
                return [
                    'status' => 403,
                    'message' => 'No tiene permisos para editar pagos en el estado actual'
                ];
            }

            $biweeklyPayment = $payroll->biweeklyPayments()->findOrFail($biweeklyId);

            // Re-calcular el pago con los datos actuales
            $calculator = new PayrollCalculatorService();
            $periods = $calculator->getPaymentPeriods(
                $payroll->getPaymentType(),
                $biweeklyPayment->biweekly_date->year,
                $biweeklyPayment->biweekly_date->month
            );

            $currentPeriod = $periods[$biweeklyPayment->biweekly - 1];

            // Re-calcular con additions/discounts actuales
            $additionalPayments = $payroll->additionalPayments()
                ->where('biweek', $biweeklyPayment->biweekly)
                ->get();

            $discountPayments = $payroll->discountPayments()
                ->where('biweek', $biweeklyPayment->biweekly)
                ->get();

            $newCalculation = $calculator->calculatePayments(
                $payroll->employee->activeContract,
                $currentPeriod,
                $additionalPayments->toArray(),
                $discountPayments->toArray()
            );

            if (!$newCalculation) {
                return [
                    'status' => 500,
                    'message' => 'Error en el cálculo del pago'
                ];
            }

            // Aplicar descuentos de afiliaciones en segunda quincena
            $affiliationDiscounts = $payroll->employee->employeeAffiliations->sum(function ($aff) use ($payroll) {
                return ($aff->percent / 100) * $payroll->employee->activeContract->real_salary;
            });

            if ($biweeklyPayment->biweekly === 2) {
                $newCalculation['bank_transfer'] -= $affiliationDiscounts;
                $newCalculation['discounts'] += $affiliationDiscounts;
            }

            // Actualizar el pago existente
            $biweeklyPayment->update([
                'accounting_amount' => $newCalculation['bank_transfer'],
                'real_amount' => $newCalculation['cash'],
                'additions' => $newCalculation['additions'] ?? 0,
                'discounts' => $newCalculation['discounts'] ?? 0,
                'worked_days' => $newCalculation['worked_days'],
                'updated_at' => now()
            ]);

            return [
                'status' => 200,
                'message' => 'Pago re-calculado exitosamente',
                'data' => [
                    'biweekly_payment' => $biweeklyPayment,
                    'calculation_details' => $newCalculation,
                    'affiliation_discounts' => $biweeklyPayment->biweekly === 2 ? $affiliationDiscounts : 0
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error al re-generar pago: ' . $e->getMessage()
            ];
        }
    }
}
