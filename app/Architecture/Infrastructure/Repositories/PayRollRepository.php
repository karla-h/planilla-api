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
use App\Models\Campaign;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\FacadesLog;

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

            // VERIFICAR CONTRATO ACTIVO
            $activeContract = Contract::where('employee_id', $employee->id)
                ->where('status_code', 'active')
                ->first();

            Log::info('Contrato activo buscado', [
                'employee_id' => $employee->id,
                'contract_found' => $activeContract ? $activeContract->id : 'null',
                'payment_type' => $activeContract ? $activeContract->payment_type : 'null'
            ]);

            if (!$activeContract) {
                return [
                    'message' => 'Employee does not have an active contract',
                    'status' => 400
                ];
            }

            // Determinar si es mensual o quincenal
            $isMonthly = $activeContract->payment_type === 'mensual';

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

            Log::info('Planilla creada exitosamente', [
                'payroll_id' => $payroll->id,
                'payment_type' => $activeContract->payment_type,
                'is_monthly' => $isMonthly
            ]);

            // ========== CORRECCIÓN PARA ADDITIONAL PAYMENTS ==========
            if (isset($request['additionalPayments']) && is_array($request['additionalPayments'])) {
                Log::info('Procesando additional payments', [
                    'count' => count($request['additionalPayments']),
                    'is_monthly' => $isMonthly
                ]);

                foreach ($request['additionalPayments'] as $add) {
                    // Para contratos mensuales, biweek debe ser null
                    $biweekValue = $isMonthly ? null : ($add['biweek'] ?? 1);

                    AdditionalPayment::create([
                        'pay_roll_id' => $payroll->id,
                        'payment_type_id' => $add['payment_type_id'],
                        'amount' => $add['amount'],
                        'quantity' => $add['quantity'] ?? 1,
                        'biweek' => $biweekValue, // ← CORREGIDO
                        'pay_card' => $add['pay_card'] ?? 1,
                    ]);

                    Log::info('Additional payment creado', [
                        'payment_type_id' => $add['payment_type_id'],
                        'amount' => $add['amount'],
                        'biweek' => $biweekValue
                    ]);
                }
            }

            // ========== CORRECCIÓN PARA DISCOUNT PAYMENTS ==========
            if (isset($request['discountPayments']) && is_array($request['discountPayments'])) {
                Log::info('Procesando discount payments', [
                    'count' => count($request['discountPayments']),
                    'is_monthly' => $isMonthly
                ]);

                foreach ($request['discountPayments'] as $disc) {
                    // Para contratos mensuales, biweek debe ser null
                    $biweekValue = $isMonthly ? null : ($disc['biweek'] ?? 1);

                    DiscountPayment::create([
                        'pay_roll_id' => $payroll->id,
                        'discount_type_id' => $disc['discount_type_id'],
                        'amount' => $disc['amount'],
                        'quantity' => $disc['quantity'] ?? 1,
                        'biweek' => $biweekValue, // ← CORREGIDO
                        'pay_card' => $disc['pay_card'] ?? 1,
                    ]);

                    Log::info('Discount payment creado', [
                        'discount_type_id' => $disc['discount_type_id'],
                        'amount' => $disc['amount'],
                        'biweek' => $biweekValue
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
                    'status' => $payroll->status,
                    'payment_type' => $activeContract->payment_type,
                    'additional_payments_count' => $payroll->additionalPayments->count(),
                    'discount_payments_count' => $payroll->discountPayments->count()
                ]
            ];
        } catch (\Throwable $th) {
            Log::error('Error en PayRollRepository@create', [
                'message' => $th-> __toString(),
                'trace' => $th->getTraceAsString()
            ]);

            return [
                'message' => 'Error, data cannot be processed: ' . $th-> __toString(),
                'status' => 500
            ];
        }
    }

    public function edit($key, $request)
{
    DB::beginTransaction();
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

        // Validar que la planilla esté abierta para editar
        if (!$payroll->isOpen()) {
            return [
                'message' => 'No se puede editar una planilla cerrada',
                'status' => 403
            ];
        }

        $activeContract = $employee->activeContract();
        $isMonthly = $activeContract && $activeContract->payment_type === 'mensual';

        // Eliminar y recrear los payments
        AdditionalPayment::where('pay_roll_id', $payroll->id)->delete();
        DiscountPayment::where('pay_roll_id', $payroll->id)->delete();

        foreach ($request['additionalPayments'] as $add) {
            $biweekValue = $isMonthly ? null : ($add['biweek'] ?? 1);

            AdditionalPayment::create([
                'pay_roll_id' => $payroll->id,
                'payment_type_id' => $add['payment_type_id'],
                'amount' => $add['amount'],
                'quantity' => $add['quantity'] ?? 1,
                'biweek' => $biweekValue,
                'pay_card' => $add['pay_card'] ?? 1,
            ]);
        }

        foreach ($request['discountPayments'] as $disc) {
            $biweekValue = $isMonthly ? null : ($disc['biweek'] ?? 1);

            DiscountPayment::create([
                'pay_roll_id' => $payroll->id,
                'discount_type_id' => $disc['discount_type_id'],
                'amount' => $disc['amount'],
                'quantity' => $disc['quantity'] ?? 1,
                'biweek' => $biweekValue,
                'pay_card' => $disc['pay_card'] ?? 1,
            ]);
        }

        DB::commit();

        return ['message' => 'PayRoll edited successfully', 'status' => 201];
    } catch (\Throwable $th) {
        DB::rollBack();
        return ['message' => 'Error processing request', 'status' => 500];
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

            $payroll = PayRoll::with([
                'employee',
                'additionalPayments.paymentType',
                'discountPayments.discountType',
                'biweeklyPayments'
                // Quitamos 'loan' y 'campaign' de la carga eager
            ])
                ->where('employee_id', $employee->id)
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $currentMonth)
                ->first();

            if (!$payroll) {
                throw new EntityNotFoundException('Payroll not found for the given month and year');
            }

            // Calcular totales
            $totaladditionals = $payroll->additionalPayments->sum(function ($payment) {
                return $payment->amount * $payment->quantity;
            });

            $totalDiscounts = $payroll->discountPayments->sum(function ($payment) {
                return $payment->amount * $payment->quantity;
            });

            // Obtener loan y campaign de forma segura
            $loan = $payroll->loan_id ? Loan::find($payroll->loan_id) : null;
            $campaign = $payroll->campaign_id ? Campaign::find($payroll->campaign_id) : null;

            $data = [
                'employee' => $payroll->employee->dni,
                'pay_date' => $payroll->created_at->format('Y-m'),
                'accounting_salary' => $payroll->accounting_salary,
                'real_salary' => $payroll->real_salary,
                'total_additionals' => $totaladditionals,
                'total_discounts' => $totalDiscounts,
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
                    'worked_days' => $biweekly->worked_days ?? null
                ])->toArray(),
                'loan' => $loan ? [
                    'amount' => $loan->amount,
                    'description' => $loan->description
                ] : null,
                'campaign' => $campaign ? [
                    'description' => $campaign->description,
                    'amount' => $campaign->amount
                ] : null
            ];

            return [
                'message' => 'Payroll data retrieved successfully',
                'data' => PayRollData::optional($data),
                'status' => 200
            ];
        } catch (EntityNotFoundException $e) {
            return [
                'message' => $e-> __toString(),
                'status' => 404
            ];
        } catch (\Throwable $th) {
            return [
                'message' => 'Error retrieving payroll data: ' . $th-> __toString(),
                'status' => 500
            ];
        }
    }

    // En PayRollRepository.php - AGREGAR ESTE MÉTODO
public function findById($id)
{
    try {
        $payroll = PayRoll::with([
            'employee',
            'additionalPayments.paymentType',
            'discountPayments.discountType',
            'biweeklyPayments',
            'loan',
            'campaign'
        ])->find($id);

        if (!$payroll) {
            throw new EntityNotFoundException('Payroll not found');
        }

        // Calcular totales
        $totalAdditionals = $payroll->additionalPayments->sum(function ($payment) {
            return $payment->amount * $payment->quantity;
        });

        $totalDiscounts = $payroll->discountPayments->sum(function ($payment) {
            return $payment->amount * $payment->quantity;
        });

        $data = [
            'id' => $payroll->id,
            'employee_id' => $payroll->employee_id,
            'employee' => [
                'dni' => $payroll->employee->dni,
                'name' => $payroll->employee->firstname . ' ' . $payroll->employee->lastname
            ],
            'period_start' => $payroll->period_start->format('Y-m-d'),
            'period_end' => $payroll->period_end->format('Y-m-d'),
            'period' => $payroll->period_start->format('Y-m') . ' a ' . $payroll->period_end->format('Y-m'),
            'status' => $payroll->status,
            'accounting_salary' => $payroll->accounting_salary,
            'real_salary' => $payroll->real_salary,
            'contract_type' => $payroll->employee->activeContract ? $payroll->employee->activeContract->payment_type : 'N/A',
            'contract_status' => $payroll->employee->activeContract ? $payroll->employee->activeContract->status_code : 'no-contract',
            'affiliation_discounts_total' => $payroll->employee->employeeAffiliations->sum(function ($aff) use ($payroll) {
                return ($aff->percent / 100) * $payroll->real_salary;
            }),
            'totals' => [
                'additionals' => $totalAdditionals,
                'discounts' => $totalDiscounts,
                'biweekly' => [
                    1 => ['additionals' => 0, 'discounts' => 0],
                    2 => ['additionals' => 0, 'discounts' => 0]
                ]
            ],
            'biweekly_count' => $payroll->biweeklyPayments->count(),
            'biweeks' => $payroll->biweeklyPayments->map(function ($biweekly) {
                return [
                    'id' => $biweekly->id,
                    'number' => $biweekly->biweekly,
                    'date' => $biweekly->biweekly_date ? $biweekly->biweekly_date->format('Y-m-d') : null,
                    'real_amount' => $biweekly->real_amount,
                    'accounting_amount' => $biweekly->accounting_amount,
                    'additionals' => $biweekly->additionals,
                    'discounts' => $biweekly->discounts,
                    'worked_days' => $biweekly->worked_days
                ];
            })->values(),
            'additional_payments' => $payroll->additionalPayments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'description' => $payment->paymentType ? $payment->paymentType->description : 'N/A',
                    'amount' => $payment->amount,
                    'quantity' => $payment->quantity,
                    'biweek' => $payment->biweek,
                    'pay_card' => $payment->pay_card
                ];
            })->values(),
            'discount_payments' => $payroll->discountPayments->map(function ($discount) {
                return [
                    'id' => $discount->id,
                    'description' => $discount->discountType ? $discount->discountType->description : 'N/A',
                    'amount' => $discount->amount,
                    'quantity' => $discount->quantity,
                    'biweek' => $discount->biweek,
                    'pay_card' => $discount->pay_card
                ];
            })->values()
        ];

        return [
            'message' => 'Payroll data retrieved successfully',
            'data' => $data,
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
            },
            'additionalPayments',
            'discountPayments',
            'biweeklyPayments'
        ])
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->get();

        // ✅ CORRECCIÓN: Incluir el ID de la planilla en la respuesta
        return DtoResponseListPayRoll::collect(
            $payrolls->map(function ($payroll) {
                $emp = $payroll->employee;

                // Calcular total de adicionales y descuentos
                $totalAdditionals = $payroll->additionalPayments->sum(function ($payment) {
                    return $payment->amount * $payment->quantity;
                });

                $totalDiscounts = $payroll->discountPayments->sum(function ($payment) {
                    return $payment->amount * $payment->quantity;
                });

                return [
                    'id' => $payroll->id, // ✅ AGREGAR ESTA LÍNEA
                    'name' => $emp->firstname . ' ' . $emp->lastname,
                    'dni' => $emp->dni,
                    'headquarter' => $emp->headquarter ? $emp->headquarter->name : 'N/A',
                    'pay_date' => $payroll->created_at->format('Y-m'),
                    'accounting_salary' => $payroll->accounting_salary,
                    'real_salary' => $payroll->real_salary,
                    'discounts' => $totalDiscounts,
                    'additionals' => $totalAdditionals,
                    'biweeklyPayments' => $payroll->biweeklyPayments->map(fn($bp) => [
                        'biweekly' => $bp->biweekly,
                        'accounting_amount' => $bp->accounting_amount,
                        'real_amount' => $bp->real_amount,
                        'additionals' => $bp->additionals,
                        'discounts' => $bp->discounts
                    ])->toArray(),
                    'status' => $payroll->status // ✅ También agregar el estado si es necesario
                ];
            })
        );
    } catch (\Throwable $th) {
        return response()->json([
            'message' => 'Error retrieving payrolls: ' . $th-> __toString(),
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
            return response()->json(['message' => 'Error: ' . $e-> __toString()], 500);
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
            return response()->json(['error' => 'Invalid input', 'message' => $e-> __toString()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch payrolls', 'message' => $e-> __toString()], 500);
        }
    }

    // En el método createForAllEmployees() - CORREGIR
public function createForAllEmployees()
{
    try {
        $currentYear = now()->year;
        $currentMonth = now()->month;

        $employees = Employee::all();

        $payrollsCreated = 0;
        $payrollsSkipped = 0;

        foreach ($employees as $employee) {
            $existingPayroll = PayRoll::where('employee_id', $employee->id)
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $currentMonth)
                ->first();

            if ($existingPayroll) {
                $payrollsSkipped++;
                continue;
            }

            // ✅ CORRECCIÓN: Obtener el contrato activo correctamente
            $activeContract = Contract::where('employee_id', $employee->id)
                ->where('status_code', 'active')
                ->first();

            if (!$activeContract) {
                $payrollsSkipped++;
                continue;
            }

            $loan = Loan::where('employee', $employee->dni)
                ->whereDate('start_date', '<=', now())
                ->whereDate('end_date', '>=', now())
                ->first();

            // ✅ CORRECCIÓN: Usar los datos del contrato activo
            $payroll = PayRoll::create([
                'accounting_salary' => $activeContract->accounting_salary,
                'real_salary' => $activeContract->real_salary,
                'employee_id' => $employee->id,
                'loan_id' => $loan ? $loan->id : null,
                'status' => PayRoll::STATUS_OPEN, // ✅ AGREGAR ESTADO
                'period_start' => now()->startOfMonth(), // ✅ AGREGAR PERIODO
                'period_end' => now()->endOfMonth()
            ]);

            $payrollsCreated++;

            // Procesar extras si existen
            $extras = Extra::where('employee', $employee->dni)
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

                $biweek = $activeContract->payment_type === 'mensual' ? null : 1;

                AdditionalPayment::create([
                    'pay_roll_id' => $payroll->id,
                    'payment_type_id' => $paymentType->id,
                    'amount' => $extra->amount,
                    'quantity' => $extra->quantity ?? 1,
                    'biweek' => $biweek,
                    'pay_card' => 1, // Valor por defecto
                ]);
            }

            // Eliminar extras procesados
            Extra::where('employee', $employee->dni)
                ->whereYear('apply_date', now()->year)
                ->whereMonth('apply_date', now()->month)
                ->delete();
        }

        return [
            'message' => "Planillas creadas exitosamente: $payrollsCreated, Omitidas: $payrollsSkipped",
            'status' => 201
        ];
    } catch (\Throwable $th) {
        Log::error('Error en createForAllEmployees', [
            'message' => $th->getMessage(),
            'trace' => $th->getTraceAsString()
        ]);
        
        return [
            'message' => 'Error procesando planillas: ' . $th->getMessage(),
            'status' => 500
        ];
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
            return ['message' => 'Error processing payrolls: ' . $th-> __toString(), 'status' => 500];
        }
    }

    /**
     * Calcular pago según tipo de contrato
     */
    public function calculatePayment($employeeId, $year, $month, $periodType = null)
    {
        try {
            $employee = Employee::with([
                'activeContract',
                'employeeAffiliations.affiliation',
                'loans' => function ($query) {
                    $query->where('start_date', '<=', now())
                        ->where('end_date', '>=', now());
                }
            ])->find($employeeId);

            if (!$employee || !$employee->activeContract) {
                return [
                    'status' => 404,
                    'message' => 'Empleado o contrato activo no encontrado'
                ];
            }

            $contract = $employee->activeContract;
            $loan = $employee->loans->first();

            // ========== CORRECCIÓN: CALCULAR AFFILIATION DISCOUNTS ==========
            $affiliationDiscounts = $employee->employeeAffiliations->sum(function ($aff) use ($contract) {
                return ($aff->percent / 100) * $contract->real_salary;
            });

            // Obtener periodos según tipo de pago
            $periods = $this->calculator->getPaymentPeriods($contract->payment_type, $year, $month);

            $results = [];

            foreach ($periods as $period) {
                // Si se especifica un periodo específico, filtrar
                if ($periodType && $period['type'] !== $periodType) {
                    continue;
                }

                $payroll = PayRoll::with(['additionalPayments', 'discountPayments', 'campaign'])
                    ->where('employee_id', $employeeId)
                    ->whereYear('period_start', $year)
                    ->whereMonth('period_start', $month)
                    ->first();

                if (!$payroll) {
                    return [
                        'status' => 404,
                        'message' => 'Planilla no encontrada para el empleado en el periodo especificado'
                    ];
                }

                $additionalPaymentsToUse = $payroll->additionalPayments;
                $discountPaymentsToUse = $payroll->discountPayments;

                // Calcular pagos INCLUYENDO CAMPAÑA
                $paymentCalculation = $this->calculator->calculatePayments(
                    $contract,
                    $period,
                    $additionalPaymentsToUse,
                    $discountPaymentsToUse,
                    $loan,
                    $payroll->campaign
                );

                if ($paymentCalculation) {
                    // Aplicar descuentos de afiliaciones
                    $affiliationApplied = 0;
                    if ($contract->payment_type === 'quincenal' && $period['type'] === 'quincena_2') {
                        $paymentCalculation['bank_transfer'] -= $affiliationDiscounts;
                        $paymentCalculation['discounts'] += $affiliationDiscounts;
                        $affiliationApplied = $affiliationDiscounts;
                    } elseif ($contract->payment_type === 'mensual') {
                        $paymentCalculation['bank_transfer'] -= $affiliationDiscounts;
                        $paymentCalculation['discounts'] += $affiliationDiscounts;
                        $affiliationApplied = $affiliationDiscounts;
                    }

                    $results[] = array_merge($period, $paymentCalculation, [
                        'employee' => [
                            'id' => $employee->id,
                            'name' => $employee->firstname . ' ' . $employee->lastname,
                            'dni' => $employee->dni
                        ],
                        'contract_type' => $contract->payment_type,
                        'affiliation_discounts' => $affiliationApplied,
                        'base_accounting_salary' => $contract->accounting_salary,
                        'base_real_salary' => $contract->real_salary,
                        'has_active_loan' => $loan ? true : false,
                        'loan_details' => $loan ? [
                            'amount' => $loan->amount,
                            'pay_card' => $loan->pay_card,
                            'biweek' => $loan->biweek
                        ] : null,
                        'has_campaign' => $payroll->campaign ? true : false,
                        'campaign_details' => $payroll->campaign ? [
                            'description' => $payroll->campaign->description,
                            'amount' => $payroll->campaign->amount,
                            'biweek' => $payroll->campaign->biweek,
                            'pay_card' => $payroll->campaign->pay_card ?? 1
                        ] : null
                    ]);
                }
            }

            return [
                'status' => 200,
                'data' => $results
            ];
        } catch (\Exception $e) {
            Log::error('Error calculando pagos', [
                'error' => $e-> __toString(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'status' => 500,
                'message' => 'Error calculando pagos: ' . $e-> __toString()
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

            if (!$payroll->isOpen()) {
                return [
                    'status' => 403,
                    'message' => 'Solo se pueden eliminar pagos cuando la planilla está ABIERTA'
                ];
            }

            $biweeklyPayment = $payroll->biweeklyPayments()->findOrFail($biweeklyId);
            $biweeklyPayment->delete();

            return [
                'status' => 200,
                'message' => 'Pago eliminado exitosamente',
                'data' => [
                    'payroll_status' => $payroll->status,
                    'remaining_payments' => $payroll->getCompletedBiweeklyPayments()
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error al eliminar pago: ' . $e-> __toString()
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

            if (!$payroll->isClosed()) {
                return [
                    'status' => 400,
                    'message' => 'Solo se pueden reabrir planillas CERRADAS'
                ];
            }

            $payroll->update(['status' => PayRoll::STATUS_OPEN]);

            return [
                'status' => 200,
                'message' => 'Planilla reabierta exitosamente - AHORA ES EDITABLE',
                'data' => $payroll
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error al reabrir planilla: ' . $e-> __toString()
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

            if (!$payroll->isOpen()) {
                return [
                    'status' => 400,
                    'message' => 'Solo se pueden cerrar planillas ABIERTAS'
                ];
            }

            $payroll->update(['status' => PayRoll::STATUS_CLOSED]);

            return [
                'status' => 200,
                'message' => 'Planilla cerrada - AHORA ES SOLO LECTURA',
                'data' => $payroll
            ];
        } catch (\Exception $e) {
            return ['status' => 500, 'message' => 'Error: ' . $e-> __toString()];
        }
    }

    public function reopenPayroll($id)
    {
        try {
            $payroll = PayRoll::findOrFail($id);
            $payroll->update(['status' => PayRoll::STATUS_OPEN]);

            return [
                'status' => 200,
                'message' => 'Planilla reabierta (editable)',
                'data' => $payroll
            ];
        } catch (\Exception $e) {
            return ['status' => 500, 'message' => 'Error: ' . $e-> __toString()];
        }
    }

    /**
     * Recalcular TODOS los pagos de la planilla
     */
    public function recalculateAllPayments($payrollId)
    {
        try {
            $payroll = PayRoll::with(['biweeklyPayments', 'employee.activeContract'])->findOrFail($payrollId);

            if (!$payroll->isOpen()) {
                return [
                    'status' => 403,
                    'message' => 'Solo se pueden recalcular pagos cuando la planilla está ABIERTA'
                ];
            }

            // Eliminar todos los pagos existentes
            $payroll->biweeklyPayments()->delete();

            // Regenerar según tipo de contrato
            $employeeId = $payroll->employee_id;
            $year = $payroll->period_start->year;
            $month = $payroll->period_start->month;

            if ($payroll->getPaymentType() === 'mensual') {
                $result = $this->generateMonthlyPayment($payroll, $payroll->employee->activeContract, $payroll->employee, null);
            } else {
                // Regenerar ambas quincenas
                $result1 = $this->generateBiweeklyPayment($payroll, $payroll->employee->activeContract, $payroll->employee, null, 1, $year, $month);
                $result2 = $this->generateBiweeklyPayment($payroll, $payroll->employee->activeContract, $payroll->employee, null, 2, $year, $month);
                $result = ['quincena_1' => $result1, 'quincena_2' => $result2];
            }

            return [
                'status' => 200,
                'message' => '✅ TODOS los pagos recalculados automáticamente',
                'data' => $result
            ];
        } catch (\Exception $e) {
            return ['status' => 500, 'message' => 'Error: ' . $e-> __toString()];
        }
    }

    /**
     * Re-generar un pago quincenal existente
     */
    public function regenerateBiweeklyPayment($payrollId, $biweeklyId)
    {
        try {
            $payroll = PayRoll::with(['employee.activeContract', 'biweeklyPayments'])->findOrFail($payrollId);

            if (!$payroll->isOpen()) {
                return [
                    'status' => 403,
                    'message' => 'Solo se pueden regenerar pagos cuando la planilla está ABIERTA'
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

            // Re-calcular con additionals/discounts actuales
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
                'additionals' => $newCalculation['additionals'] ?? 0,
                'discounts' => $newCalculation['discounts'] ?? 0,
                'worked_days' => $newCalculation['worked_days'],
                'updated_at' => now()
            ]);

            return [
                'status' => 200,
                'message' => '✅ Pago re-calculado automáticamente con los datos actuales',
                'data' => [
                    'biweekly_payment' => $biweeklyPayment,
                    'calculation_details' => $newCalculation,
                    'affiliation_discounts' => $biweeklyPayment->biweekly === 2 ? $affiliationDiscounts : 0
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error al re-generar pago: ' . $e-> __toString()
            ];
        }
    }

    /**
     * Generar pagos según tipo de contrato
     */
    public function generatePayments($employeeId, $year, $month, $biweekly = null)
    {
        try {
            $employee = Employee::with([
                'activeContract',
                'employeeAffiliations',
                'loans' => function ($query) {
                    $query->where('start_date', '<=', now())
                        ->where('end_date', '>=', now());
                }
            ])->find($employeeId);

            if (!$employee || !$employee->activeContract) {
                return [
                    'status' => 404,
                    'message' => 'Empleado o contrato activo no encontrado'
                ];
            }

            $contract = $employee->activeContract;
            $payroll = PayRoll::with(['additionalPayments', 'discountPayments'])
                ->where('employee_id', $employeeId)
                ->whereYear('period_start', $year)
                ->whereMonth('period_start', $month)
                ->first();

            if (!$payroll) {
                return [
                    'status' => 404,
                    'message' => 'No se encontró planilla para este mes'
                ];
            }

            $loan = $employee->loans->first();

            // Para contratos mensuales, solo un pago
            if ($contract->payment_type === 'mensual') {
                return $this->generateMonthlyPayment($payroll, $contract, $employee, $loan);
            }

            // Para contratos quincenales
            if ($contract->payment_type === 'quincenal') {
                // Validar que se especifique la quincena
                if (!$biweekly) {
                    return [
                        'status' => 400,
                        'message' => 'Para contratos quincenales debe especificar la quincena (1 o 2)'
                    ];
                }

                // Validar que la quincena sea 1 o 2
                if (!in_array($biweekly, [1, 2])) {
                    return [
                        'status' => 400,
                        'message' => 'La quincena debe ser 1 o 2'
                    ];
                }

                return $this->generateBiweeklyPayment($payroll, $contract, $employee, $loan, $biweekly, $year, $month);
            }

            return [
                'status' => 400,
                'message' => 'Tipo de contrato no válido'
            ];
        } catch (\Exception $e) {
            Log::error('Error generando pagos', [
                'error' => $e-> __toString(),
                'employeeId' => $employeeId
            ]);
            return [
                'status' => 500,
                'message' => 'Error generando pago: ' . $e-> __toString()
            ];
        }
    }

    /**
     * Generar pago mensual
     */
    private function generateMonthlyPayment($payroll, $contract, $employee, $loan)
    {
        $existingPayment = $payroll->biweeklyPayments()->first();
        if ($existingPayment) {
            return [
                'status' => 409,
                'message' => 'El pago mensual ya existe'
            ];
        }

        $period = [
            'start' => $payroll->period_start->format('Y-m-d'),
            'end' => $payroll->period_end->format('Y-m-d'),
            'type' => 'mensual'
        ];

        // Cargar los payments con las relaciones INCLUYENDO CAMPAÑA
        $payrollWithPayments = PayRoll::with([
            'additionalPayments.paymentType',
            'discountPayments.discountType',
            'campaign'
        ])->find($payroll->id);

        $paymentCalculation = $this->calculator->calculatePayments(
            $contract,
            $period,
            $payrollWithPayments->additionalPayments,
            $payrollWithPayments->discountPayments,
            $loan,
            $payrollWithPayments->campaign
        );

        if (!$paymentCalculation) {
            return [
                'status' => 500,
                'message' => 'Error en el cálculo del pago'
            ];
        }

        $additionalsForMonth = $paymentCalculation['additionals_detail']['total'] ?? $paymentCalculation['additionals'] ?? 0;
        $discountsForMonth = $paymentCalculation['discounts_detail']['total'] ?? $paymentCalculation['discounts'] ?? 0;
        $campaignForMonth = $paymentCalculation['campaign_detail']['total'] ?? $paymentCalculation['campaign'] ?? 0;

        // Calcular afiliaciones
        $affiliationDiscounts = $employee->employeeAffiliations->sum(function ($aff) use ($contract) {
            return ($aff->percent / 100) * $contract->real_salary;
        });

        $paymentCalculation['bank_transfer'] -= $affiliationDiscounts;
        $paymentCalculation['discounts'] += $affiliationDiscounts;

        // CREAR PAGO
        $payment = $payroll->biweeklyPayments()->create([
            'biweekly' => 1,
            'biweekly_date' => now(),
            'accounting_amount' => $paymentCalculation['bank_transfer'],
            'real_amount' => $paymentCalculation['cash'],
            'additionals' => $additionalsForMonth + $campaignForMonth,
            'discounts' => $discountsForMonth + $affiliationDiscounts,
            'worked_days' => $paymentCalculation['worked_days']
        ]);

        $payroll->update(['status' => PayRoll::STATUS_OPEN]);

        return [
            'status' => 201,
            'message' => 'Pago mensual generado exitosamente',
            'data' => array_merge($paymentCalculation, [
                'payment_id' => $payment->id,
                'payroll_status' => $payroll->status,
                'affiliation_discounts' => $affiliationDiscounts,
                'stored_additionals' => $additionalsForMonth + $campaignForMonth,
                'stored_discounts' => $discountsForMonth + $affiliationDiscounts
            ])
        ];
    }

    private function generateBiweeklyPayment($payroll, $contract, $employee, $loan, $biweekly, $year, $month)
    {
        $existingPayment = $payroll->biweeklyPayments()->where('biweekly', $biweekly)->first();
        if ($existingPayment) {
            return [
                'status' => 409,
                'message' => "El pago quincenal {$biweekly} ya existe"
            ];
        }

        $periods = $this->calculator->getPaymentPeriods('quincenal', $year, $month);
        $currentPeriod = $periods[$biweekly - 1];

        // Cargar los payments con las relaciones INCLUYENDO CAMPAÑA
        $payrollWithPayments = PayRoll::with([
            'additionalPayments.paymentType',
            'discountPayments.discountType',
            'campaign'
        ])->find($payroll->id);

        $paymentCalculation = $this->calculator->calculatePayments(
            $contract,
            $currentPeriod,
            $payrollWithPayments->additionalPayments,
            $payrollWithPayments->discountPayments,
            $loan,
            $payrollWithPayments->campaign
        );

        if (!$paymentCalculation) {
            return [
                'status' => 500,
                'message' => 'Error en el cálculo del pago'
            ];
        }

        $additionalsForBiweekly = $paymentCalculation['additionals_detail']['total'] ?? $paymentCalculation['additionals'] ?? 0;
        $discountsForBiweekly = $paymentCalculation['discounts_detail']['total'] ?? $paymentCalculation['discounts'] ?? 0;
        $campaignForBiweekly = $paymentCalculation['campaign_detail']['total'] ?? $paymentCalculation['campaign'] ?? 0;

        // Calcular afiliaciones solo en quincena 2
        $affiliationDiscounts = 0;
        if ($biweekly === 2) {
            $affiliationDiscounts = $employee->employeeAffiliations->sum(function ($aff) use ($contract) {
                return ($aff->percent / 100) * $contract->real_salary;
            });
            $paymentCalculation['bank_transfer'] -= $affiliationDiscounts;
            $paymentCalculation['discounts'] += $affiliationDiscounts;
        }

        // CREAR PAGO
        $payment = $payroll->biweeklyPayments()->create([
            'biweekly' => $biweekly,
            'biweekly_date' => now(),
            'accounting_amount' => $paymentCalculation['bank_transfer'],
            'real_amount' => $paymentCalculation['cash'],
            'additionals' => $additionalsForBiweekly + $campaignForBiweekly,
            'discounts' => $discountsForBiweekly + $affiliationDiscounts,
            'worked_days' => $paymentCalculation['worked_days']
        ]);

        $payroll->update(['status' => PayRoll::STATUS_OPEN]);

        return [
            'status' => 201,
            'message' => "Pago quincenal {$biweekly} generado exitosamente",
            'data' => array_merge($paymentCalculation, [
                'payment_id' => $payment->id,
                'payroll_status' => $payroll->status,
                'affiliation_discounts' => $affiliationDiscounts,
                'stored_additionals' => $additionalsForBiweekly + $campaignForBiweekly,
                'stored_discounts' => $discountsForBiweekly + $affiliationDiscounts,
                'completed_payments' => $payroll->getCompletedBiweeklyPayments(),
                'expected_payments' => $payroll->getExpectedBiweeklyPayments()
            ])
        ];
    }
}
