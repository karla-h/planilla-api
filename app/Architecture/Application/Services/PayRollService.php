<?php

namespace App\Architecture\Application\Services;

use App\Architecture\Domain\Models\UseCases\IPayRollUseCase;
use App\Architecture\Infrastructure\Repositories\PayRollRepository;
use App\Models\Employee;
use App\Models\PayRoll;

class PayRollService implements IPayRollUseCase
{
    public function __construct(
        protected PayRollRepository $repository
    ) {}

    public function findAll($request)
    {
        return $this->repository->findAll($request);
    }

    public function create($request)
    {
        // Cambiar de $request->validated() a solo $request
        return $this->repository->create($request);
    }

    public function findBy($key)
    {
        return $this->repository->findBy($key);
    }

    public function edit($key, $request)
    {
        // Aquí también cambiar si es necesario
        return $this->repository->edit($key, $request);
    }

    public function delete($key)
    {
        return $this->repository->delete($key);
    }

    public function findByEmployeeAndPaydate($dni, $pay_date)
    {
        return $this->repository->findByEmployeeAndPaydate($dni, $pay_date);
    }

    public function generatePayRolls($headquarter, $pay_date)
    {
        return $this->repository->generatePayRolls($headquarter, $pay_date);
    }

    public function createForAllEmployees()
    {
        return $this->repository->createForAllEmployees();
    }

    public function createPayrollsForSpecificEmployees($request)
    {
        return $this->repository->createPayrollsForSpecificEmployees($request);
    }

    public function calculatePayment($employeeId, $year, $month, $periodType = null)
    {
        return $this->repository->calculatePayment($employeeId, $year, $month, $periodType);
    }


    public function regenerateBiweeklyPayment($payrollId, $biweeklyId)
    {
        return $this->repository->regenerateBiweeklyPayment($payrollId, $biweeklyId);
    }

    public function deleteBiweeklyPayment($payrollId, $biweeklyId)
    {
        return $this->repository->deleteBiweeklyPayment($payrollId, $biweeklyId);
    }

    public function openPayroll($id)
    {
        return $this->repository->openPayroll($id);
    }

    public function closePayroll($id)
    {
        return $this->repository->closePayroll($id);
    }

    /**
     * Generar pagos según tipo de contrato
     */
    public function generatePayments($employeeId, $year, $month, $biweekly = null)
    {
        return $this->repository->generatePayments($employeeId, $year, $month, $biweekly);
    }

   /**
     * Obtener planillas de un empleado con cálculos detallados
     */
    public function getEmployeePayrolls($dni, $filters = [])
    {
        try {
            $employee = Employee::where('dni', $dni)->first();

            if (!$employee) {
                return [
                    'status' => 404,
                    'message' => 'Empleado no encontrado'
                ];
            }

            $year = $filters['year'] ?? null;
            $month = $filters['month'] ?? null;

            $query = PayRoll::with([
                'biweeklyPayments',
                'additionalPayments.paymentType',
                'discountPayments.discountType',
                'employee'
            ])
                ->where('employee_id', $employee->id);

            if ($year) {
                $query->whereYear('period_start', $year);
            }
            if ($month) {
                $query->whereMonth('period_start', $month);
            }

            $payrolls = $query->orderBy('period_start', 'desc')
                ->get()
                ->map(function ($payroll) {
                    $activeContract = $payroll->employee->activeContract()
                        ->where('status_code', 'active')
                        ->first();

                    $affiliationDiscounts = 0;

                    if ($activeContract) {
                        $affiliationDiscounts = $payroll->employee->employeeAffiliations->sum(function ($aff) use ($activeContract) {
                            return ($aff->percent / 100) * $activeContract->real_salary;
                        });
                    }

                    // Calcular totales
                    $totalAdditionals = $payroll->additionalPayments->sum(function ($payment) {
                        return $payment->amount * $payment->quantity;
                    });

                    $totalDiscounts = $payroll->discountPayments->sum(function ($payment) {
                        return $payment->amount * $payment->quantity;
                    });

                    // Calcular totales por quincena
                    $biweeklyTotals = [];
                    foreach ([1, 2] as $biweek) {
                        $biweeklyAdditionals = $payroll->additionalPayments
                            ->where('biweek', $biweek)
                            ->sum(function ($payment) {
                                return $payment->amount * $payment->quantity;
                            });

                        $biweeklyDiscounts = $payroll->discountPayments
                            ->where('biweek', $biweek)
                            ->sum(function ($payment) {
                                return $payment->amount * $payment->quantity;
                            });

                        $biweeklyTotals[$biweek] = [
                            'additionals' => $biweeklyAdditionals,
                            'discounts' => $biweeklyDiscounts
                        ];
                    }

                    return [
                        'id' => $payroll->id,
                        'period' => $payroll->period_start->format('Y-m') . ' a ' . $payroll->period_end->format('Y-m'),
                        'status' => $payroll->status,
                        'accounting_salary' => $payroll->accounting_salary,
                        'real_salary' => $payroll->real_salary,
                        'contract_type' => $activeContract ? $activeContract->payment_type : 'N/A',
                        'contract_status' => $activeContract ? $activeContract->status_code : 'no-contract',
                        'affiliation_discounts_total' => $affiliationDiscounts,
                        'totals' => [
                            'additionals' => $totalAdditionals,
                            'discounts' => $totalDiscounts,
                            'biweekly' => $biweeklyTotals
                        ],
                        'biweekly_count' => $payroll->biweeklyPayments->count(),
                        'biweeks' => $payroll->biweeklyPayments->map(function ($biweekly) {
                            // CORREGIDO: Manejar fechas como string o Carbon
                            $biweeklyDate = $biweekly->biweekly_date;
                            $formattedDate = $biweeklyDate ? 
                                (is_string($biweeklyDate) ? $biweeklyDate : $biweeklyDate->format('Y-m-d')) 
                                : null;

                            return [
                                'id' => $biweekly->id,
                                'number' => $biweekly->biweekly,
                                'date' => $formattedDate,
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
                });

            return [
                'status' => 200,
                'data' => [
                    'employee' => [
                        'dni' => $employee->dni,
                        'name' => $employee->firstname . ' ' . $employee->lastname
                    ],
                    'payrolls' => $payrolls
                ],
                'filters' => [
                    'year' => $year,
                    'month' => $month
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error al obtener planillas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener quincenas de un empleado con cálculos detallados
     */
    public function getEmployeeBiweeks($dni, $filters = [])
    {
        try {
            $employee = Employee::where('dni', $dni)->first();

            if (!$employee) {
                return [
                    'status' => 404,
                    'message' => 'Empleado no encontrado'
                ];
            }

            $year = $filters['year'] ?? null;
            $month = $filters['month'] ?? null;

            $query = PayRoll::with([
                'biweeklyPayments',
                'additionalPayments.paymentType',
                'discountPayments.discountType',
                'employee'
            ])
                ->where('employee_id', $employee->id)
                ->whereHas('biweeklyPayments');

            if ($year) {
                $query->whereYear('period_start', $year);
            }
            if ($month) {
                $query->whereMonth('period_start', $month);
            }

            $biweeks = $query->get()
                ->flatMap(function ($payroll) {
                    $activeContract = $payroll->employee->activeContract()
                        ->where('status_code', 'active')
                        ->first();

                    return $payroll->biweeklyPayments->map(function ($biweekly) use ($payroll, $activeContract) {
                        // CORREGIDO: Manejar fechas como string o Carbon
                        $biweeklyDate = $biweekly->biweekly_date;
                        $formattedDate = $biweeklyDate ? 
                            (is_string($biweeklyDate) ? $biweeklyDate : $biweeklyDate->format('Y-m-d')) 
                            : null;

                        // Obtener adicionales y descuentos específicos de esta quincena
                        $biweeklyAdditionals = $payroll->additionalPayments
                            ->where('biweek', $biweekly->biweekly)
                            ->map(function ($payment) {
                                return [
                                    'description' => $payment->paymentType ? $payment->paymentType->description : 'N/A',
                                    'amount' => $payment->amount,
                                    'quantity' => $payment->quantity
                                ];
                            });

                        $biweeklyDiscounts = $payroll->discountPayments
                            ->where('biweek', $biweekly->biweekly)
                            ->map(function ($discount) {
                                return [
                                    'description' => $discount->discountType ? $discount->discountType->description : 'N/A',
                                    'amount' => $discount->amount,
                                    'quantity' => $discount->quantity
                                ];
                            });

                        return [
                            'payroll_id' => $payroll->id,
                            'payroll_period' => $payroll->period_start->format('Y-m'),
                            'payroll_status' => $payroll->status,
                            'contract_type' => $activeContract ? $activeContract->payment_type : 'N/A',
                            'contract_status' => $activeContract ? $activeContract->status_code : 'no-contract',

                            'biweekly_id' => $biweekly->id,
                            'biweekly_number' => $biweekly->biweekly,
                            'date' => $formattedDate,

                            'amounts' => [
                                'real_amount' => $biweekly->real_amount,
                                'accounting_amount' => $biweekly->accounting_amount,
                                'additionals' => $biweekly->additionals,
                                'discounts' => $biweekly->discounts
                            ],

                            'worked_days' => $biweekly->worked_days,

                            'additionals_detail' => $biweeklyAdditionals->values(),
                            'discounts_detail' => $biweeklyDiscounts->values(),

                            'employee' => [
                                'dni' => $payroll->employee->dni,
                                'name' => $payroll->employee->firstname . ' ' . $payroll->employee->lastname
                            ]
                        ];
                    });
                })
                ->sortByDesc('date')
                ->values();

            return [
                'status' => 200,
                'data' => [
                    'employee' => [
                        'dni' => $employee->dni,
                        'name' => $employee->firstname . ' ' . $employee->lastname
                    ],
                    'biweeks' => $biweeks
                ],
                'filters' => [
                    'year' => $year,
                    'month' => $month
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error al obtener quincenas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener quincenas de una planilla específica con cálculos detallados
     */
    public function getPayrollBiweeks($payrollId, $filters = [])
    {
        try {
            $payroll = PayRoll::with([
                'biweeklyPayments',
                'additionalPayments.paymentType',
                'discountPayments.discountType',
                'employee',
                'loan',
                'campaign'
            ])->find($payrollId);

            if (!$payroll) {
                return [
                    'status' => 404,
                    'message' => 'Planilla no encontrada'
                ];
            }

            $activeContract = $payroll->employee->activeContract()
                ->where('status_code', 'active')
                ->first();

            $affiliationDiscounts = 0;

            if ($activeContract) {
                $affiliationDiscounts = $payroll->employee->employeeAffiliations->sum(function ($aff) use ($activeContract) {
                    return ($aff->percent / 100) * $activeContract->real_salary;
                });
            }

            $year = $filters['year'] ?? null;
            $month = $filters['month'] ?? null;

            $biweeks = $payroll->biweeklyPayments()
                ->when($year, function ($query) use ($year) {
                    return $query->whereYear('biweekly_date', $year);
                })
                ->when($month, function ($query) use ($month) {
                    return $query->whereMonth('biweekly_date', $month);
                })
                ->get()
                ->map(function ($biweekly) use ($payroll, $activeContract, $affiliationDiscounts) {
                    // CORREGIDO: Manejar fechas como string o Carbon
                    $biweeklyDate = $biweekly->biweekly_date;
                    $formattedDate = $biweeklyDate ? 
                        (is_string($biweeklyDate) ? $biweeklyDate : $biweeklyDate->format('Y-m-d')) 
                        : null;

                    // Calcular adicionales y descuentos específicos de esta quincena
                    $biweeklyAdditionals = $payroll->additionalPayments
                        ->where('biweek', $biweekly->biweekly)
                        ->map(function ($payment) {
                            return [
                                'id' => $payment->id,
                                'description' => $payment->paymentType ? $payment->paymentType->description : 'N/A',
                                'amount' => $payment->amount,
                                'quantity' => $payment->quantity,
                                'pay_card' => $payment->pay_card
                            ];
                        });

                    $biweeklyDiscounts = $payroll->discountPayments
                        ->where('biweek', $biweekly->biweekly)
                        ->map(function ($discount) {
                            return [
                                'id' => $discount->id,
                                'description' => $discount->discountType ? $discount->discountType->description : 'N/A',
                                'amount' => $discount->amount,
                                'quantity' => $discount->quantity,
                                'pay_card' => $discount->pay_card
                            ];
                        });

                    // Calcular si aplica descuento de afiliaciones
                    $appliesAffiliation = $activeContract &&
                        (($activeContract->payment_type === 'quincenal' && $biweekly->biweekly === 2) ||
                            ($activeContract->payment_type === 'mensual'));

                    return [
                        'id' => $biweekly->id,
                        'number' => $biweekly->biweekly,
                        'date' => $formattedDate,

                        'amounts' => [
                            'real_amount' => $biweekly->real_amount,
                            'accounting_amount' => $biweekly->accounting_amount,
                            'additionals' => $biweekly->additionals,
                            'discounts' => $biweekly->discounts,
                            'affiliation_discounts' => $appliesAffiliation ? $affiliationDiscounts : 0
                        ],

                        'worked_days' => $biweekly->worked_days,

                        'additionals_detail' => $biweeklyAdditionals->values(),
                        'discounts_detail' => $biweeklyDiscounts->values(),

                        'contract_info' => [
                            'type' => $activeContract ? $activeContract->payment_type : 'N/A',
                            'status' => $activeContract ? $activeContract->status_code : 'no-contract',
                            'accounting_salary' => $activeContract ? $activeContract->accounting_salary : 0,
                            'real_salary' => $activeContract ? $activeContract->real_salary : 0
                        ]
                    ];
                });

            return [
                'status' => 200,
                'data' => [
                    'payroll' => [
                        'id' => $payroll->id,
                        'period' => $payroll->period_start->format('Y-m') . ' a ' . $payroll->period_end->format('Y-m'),
                        'status' => $payroll->status,
                        'contract_type' => $activeContract ? $activeContract->payment_type : 'N/A',
                        'contract_status' => $activeContract ? $activeContract->status_code : 'no-contract',
                        'affiliation_discounts_total' => $affiliationDiscounts
                    ],
                    'employee' => [
                        'dni' => $payroll->employee->dni,
                        'name' => $payroll->employee->firstname . ' ' . $payroll->employee->lastname
                    ],
                    'loan' => $payroll->loan ? [
                        'amount' => $payroll->loan->amount,
                        'description' => $payroll->loan->description
                    ] : null,
                    'campaign' => $payroll->campaign ? [
                        'description' => $payroll->campaign->description,
                        'amount' => $payroll->campaign->amount
                    ] : null,
                    'biweeks' => $biweeks
                ],
                'filters' => [
                    'year' => $year,
                    'month' => $month
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error al obtener quincenas de la planilla: ' . $e->getMessage()
            ];
        }
    }
}
