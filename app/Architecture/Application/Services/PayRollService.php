<?php

namespace App\Architecture\Application\Services;

use App\Architecture\Domain\Models\UseCases\IPayRollUseCase;
use App\Architecture\Infrastructure\Repositories\PayRollRepository;
use App\Models\AdditionalPayment;
use App\Models\DiscountPayment;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\PayRoll;
use Illuminate\Support\Facades\Log;

class PayRollService implements IPayRollUseCase
{
    protected $editService;
    protected $calculator;

    public function __construct(
        protected PayRollRepository $repository,
        PayrollCalculatorService $calculator
    ) {
        $this->editService = new PayrollEditService();
        $this->calculator = $calculator;
    }

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
    public function findById($id)
    {
        return $this->repository->findById($id);
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

    public function closePayroll($payrollId)
    {
        try {
            $payroll = PayRoll::find($payrollId);
            if (!$payroll) return ['status' => 404, 'message' => 'Planilla no encontrada'];

            $payroll->update(['status' => PayRoll::STATUS_CLOSED]);

            return ['status' => 200, 'message' => 'Planilla cerrada', 'data' => $payroll];
        } catch (\Exception $e) {
            return ['status' => 500, 'message' => 'Error: ' . $e->getMessage()];
        }
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

    /**
     * Generar planillas masivas con filtros - VERSIÓN MEJORADA
     */
    public function generateMassPayrolls($filters)
    {
        try {
            // ✅ CORREGIDO: Pasar el array completo al repository
            return $this->repository->generateMassPayrolls($filters);
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error en generación masiva: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Pre-calcular pago para frontend (usa calculatePayments existente)
     */
    public function previewPayment($employeeId, $year, $month, $biweekly = null, $additionalPayments = [], $discountPayments = [])
    {
        try {
            Log::info("🔍 Preview Payment - Buscando datos", [
                'employee_id' => $employeeId,
                'year' => $year,
                'month' => $month,
                'biweekly' => $biweekly
            ]);

            // Primero obtener el empleado para tener su DNI
            $employee = Employee::with([
                'activeContract',
                'employeeAffiliations.affiliation'
            ])->find($employeeId);

            if (!$employee) {
                return [
                    'status' => 404,
                    'message' => 'Empleado no encontrado'
                ];
            }

            $contract = $employee->activeContract;

            if (!$contract) {
                return [
                    'status' => 400,
                    'message' => 'El empleado no tiene un contrato activo'
                ];
            }

            // ✅ CORREGIDO: Buscar loans por DNI del empleado
            $loans = Loan::where('employee', $employee->dni)
                ->where(function ($q) {
                    $q->where('end_date', '>=', now())
                        ->orWhereNull('end_date');
                })
                ->get();

            $loan = $loans->first();

            Log::info("🔍 Datos cargados:", [
                'employee' => $employee->firstname . ' ' . $employee->lastname,
                'dni' => $employee->dni,
                'contract' => $contract->payment_type,
                'loan_exists' => $loan ? 'Sí' : 'No',
                'loan_count' => $loans->count(),
                'loan_details' => $loan
            ]);

            // ✅ BUSCAR PLANILLA EXISTENTE para obtener adicionales y descuentos
            $existingPayroll = PayRoll::where('employee_id', $employeeId)
                ->whereYear('period_start', $year)
                ->whereMonth('period_start', $month)
                ->with(['additionalPayments', 'discountPayments'])
                ->first();

            Log::info("🔍 Planilla existente encontrada:", [
                'payroll_id' => $existingPayroll ? $existingPayroll->id : 'No encontrada',
                'additional_count' => $existingPayroll ? $existingPayroll->additionalPayments->count() : 0,
                'discount_count' => $existingPayroll ? $existingPayroll->discountPayments->count() : 0
            ]);

            // ✅ USAR adicionales y descuentos de la planilla existente
            $existingAdditionals = $existingPayroll ? $existingPayroll->additionalPayments : collect();
            $existingDiscounts = $existingPayroll ? $existingPayroll->discountPayments : collect();

            // ✅ FILTRAR por quincena si es necesario
            if ($biweekly) {
                $existingAdditionals = $existingAdditionals->filter(function ($item) use ($biweekly) {
                    return $item->biweek == $biweekly || $item->biweek === null;
                });
                $existingDiscounts = $existingDiscounts->filter(function ($item) use ($biweekly) {
                    return $item->biweek == $biweekly || $item->biweek === null;
                });
            }

            // Convertir a arrays para el calculator
            $additionalPaymentsArray = $existingAdditionals->map(function ($additional) {
                return [
                    'amount' => $additional->amount,
                    'quantity' => $additional->quantity,
                    'pay_card' => $additional->pay_card,
                    'biweek' => $additional->biweek,
                    'payment_type_id' => $additional->payment_type_id
                ];
            })->toArray();

            $discountPaymentsArray = $existingDiscounts->map(function ($discount) {
                return [
                    'amount' => $discount->amount,
                    'quantity' => $discount->quantity,
                    'pay_card' => $discount->pay_card,
                    'biweek' => $discount->biweek,
                    'discount_type_id' => $discount->discount_type_id,
                    'is_advance' => $discount->is_advance
                ];
            })->toArray();

            Log::info("🔍 Pagos a procesar:", [
                'additionals_count' => count($additionalPaymentsArray),
                'discounts_count' => count($discountPaymentsArray),
                'additionals' => $additionalPaymentsArray,
                'discounts' => $discountPaymentsArray
            ]);

            // ✅ USAR EL CALCULATOR INYECTADO para obtener periodos
            if ($contract->payment_type === 'mensual') {
                $periods = $this->calculator->getPaymentPeriods('mensual', $year, $month);
                $targetPeriod = $periods[0];
            } else {
                $periods = $this->calculator->getPaymentPeriods('quincenal', $year, $month);
                $targetPeriod = $biweekly ? $periods[$biweekly - 1] : $periods[0];
            }

            Log::info("🎯 Periodo objetivo:", $targetPeriod);

            // ✅ USAR EL CALCULATOR INYECTADO para calcular pagos
            $paymentCalculation = $this->calculator->calculatePayments(
                $contract,
                $targetPeriod,
                $additionalPaymentsArray,
                $discountPaymentsArray,
                $loan, // ✅ Ahora $loan está correctamente cargado
                null
            );

            if (!$paymentCalculation) {
                return [
                    'status' => 500,
                    'message' => 'Error en el cálculo del pago'
                ];
            }

            Log::info("✅ Cálculo completado:", $paymentCalculation);

            return [
                'status' => 200,
                'data' => array_merge($targetPeriod, $paymentCalculation, [
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $employee->firstname . ' ' . $employee->lastname,
                        'dni' => $employee->dni
                    ],
                    'contract_type' => $contract->payment_type
                ])
            ];
        } catch (\Exception $e) {
            Log::error('❌ Error en previewPayment: ' . $e->getMessage());
            Log::error('❌ Stack trace: ' . $e->getTraceAsString());
            return [
                'status' => 500,
                'message' => 'Error en pre-cálculo: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generar pagos quincenales masivos - VERSIÓN MEJORADA
     */
    /**
     * Generar pagos quincenales masivos - VERSIÓN CORREGIDA
     */
    public function generateMassBiweeklyPayments($filters)
    {
        try {
            // ✅ CORREGIDO: Pasar el array completo de filters al repository
            return $this->repository->generateMassBiweeklyPayments($filters);
        } catch (\Exception $e) {
            Log::error('Error en generateMassBiweeklyPayments service', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            return [
                'status' => 500,
                'message' => 'Error en generación masiva de pagos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener estado de planillas por mes para dashboard
     */
    public function getPayrollsStatusByMonth($year, $month)
    {
        try {
            // Obtener todas las planillas del mes
            $payrolls = PayRoll::with([
                'employee',
                'biweeklyPayments'
            ])
                ->whereYear('period_start', $year)
                ->whereMonth('period_start', $month)
                ->get();

            // Contadores globales
            $summary = [
                'total_planillas' => $payrolls->count(),
                'planillas_abiertas' => 0,
                'planillas_cerradas' => 0,
                'quincenas' => [
                    'q1' => ['pagadas' => 0, 'pendientes' => 0],
                    'q2' => ['pagadas' => 0, 'pendientes' => 0]
                ]
            ];

            // Procesar cada planilla
            $planillasStatus = $payrolls->map(function ($payroll) use (&$summary) {
                // Estado de la planilla
                $planillaAbierta = $payroll->status === PayRoll::STATUS_OPEN;

                if ($planillaAbierta) {
                    $summary['planillas_abiertas']++;
                } else {
                    $summary['planillas_cerradas']++;
                }

                // Estado de quincenas
                $q1Payment = $payroll->biweeklyPayments->where('biweekly', 1)->first();
                $q2Payment = $payroll->biweeklyPayments->where('biweekly', 2)->first();

                $q1Status = $q1Payment ? 'Pagada' : 'Pendiente de pago';
                $q2Status = $q2Payment ? 'Pagada' : 'Pendiente de pago';

                // Actualizar contadores globales
                if ($q1Status === 'Pagada') {
                    $summary['quincenas']['q1']['pagadas']++;
                } else {
                    $summary['quincenas']['q1']['pendientes']++;
                }

                if ($q2Status === 'Pagada') {
                    $summary['quincenas']['q2']['pagadas']++;
                } else {
                    $summary['quincenas']['q2']['pendientes']++;
                }

                // Determinar estado global del mes
                $estadoGlobal = 'CERRADA';
                if ($planillaAbierta) {
                    $estadoGlobal = 'ABIERTA';
                }

                return [
                    'planilla_id' => $payroll->id,
                    'empleado' => [
                        'id' => $payroll->employee->id,
                        'nombre' => $payroll->employee->firstname . ' ' . $payroll->employee->lastname,
                        'dni' => $payroll->employee->dni
                    ],
                    'estado_global' => $estadoGlobal,
                    'quincenas' => [
                        'q1' => [
                            'estado' => $q1Status,
                            'pago_id' => $q1Payment ? $q1Payment->id : null,
                            'monto' => $q1Payment ? $q1Payment->real_amount + $q1Payment->accounting_amount : 0
                        ],
                        'q2' => [
                            'estado' => $q2Status,
                            'pago_id' => $q2Payment ? $q2Payment->id : null,
                            'monto' => $q2Payment ? $q2Payment->real_amount + $q2Payment->accounting_amount : 0
                        ]
                    ],
                    'tipo_contrato' => $payroll->employee->activeContract ? $payroll->employee->activeContract->payment_type : 'N/A',
                    'salario' => $payroll->real_salary
                ];
            });

            // Determinar estado general del mes
            $estadoGeneralMes = 'CERRADA';
            if ($summary['planillas_abiertas'] > 0) {
                $estadoGeneralMes = 'ABIERTA';
            }

            return [
                'status' => 200,
                'data' => [
                    'periodo' => [
                        'mes' => $month,
                        'año' => $year,
                        'nombre_mes' => $this->getMonthName($month),
                        'estado_general' => $estadoGeneralMes
                    ],
                    'resumen' => $summary,
                    'planillas' => $planillasStatus->values(),
                    'acciones_disponibles' => $this->getAvailableActions($estadoGeneralMes, $summary)
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Error obteniendo estado de planillas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener nombre del mes
     */
    private function getMonthName($month)
    {
        $months = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre'
        ];

        return $months[$month] ?? 'Mes desconocido';
    }

    /**
     * Determinar acciones disponibles según el estado
     */
    private function getAvailableActions($estadoGeneral, $summary)
    {
        $actions = [];

        if ($estadoGeneral === 'ABIERTA') {
            $actions[] = 'generar_pagos_q1';
            $actions[] = 'generar_pagos_q2';
            $actions[] = 'cerrar_planillas';

            if ($summary['quincenas']['q1']['pendientes'] > 0) {
                $actions[] = 'generar_pagos_masivos_q1';
            }

            if ($summary['quincenas']['q2']['pendientes'] > 0) {
                $actions[] = 'generar_pagos_masivos_q2';
            }
        } else {
            $actions[] = 'reabrir_planillas';
            $actions[] = 'ver_reportes';
        }

        return $actions;
    }

    public function checkEditPermissions($payrollId, $biweekly = null)
    {
        return $this->editService->canEditPayroll($payrollId, $biweekly);
    }

    public function createAdvance($payrollId, $data)
    {
        $canEdit = $this->editService->canEditPayroll($payrollId, $data['biweek'] ?? null);
        if (!$canEdit['can_edit']) {
            return ['status' => 400, 'message' => $canEdit['reason']];
        }

        return $this->editService->createOrUpdateAdvance($payrollId, $data);
    }

    public function addPaymentToPayroll($payrollId, $type, $data, $biweekly = null)
    {
        $canEdit = $this->editService->canEditPayroll($payrollId, $biweekly);
        if (!$canEdit['can_edit']) {
            return ['status' => 400, 'message' => $canEdit['reason']];
        }

        $result = null;

        switch ($type) {
            case 'additional':
                $result = AdditionalPayment::create(array_merge($data, [
                    'pay_roll_id' => $payrollId,
                    'biweek' => $biweekly
                ]));
                break;
            case 'discount':
                $result = DiscountPayment::create(array_merge($data, [
                    'pay_roll_id' => $payrollId,
                    'biweek' => $biweekly,
                    'is_advance' => false
                ]));
                break;
            case 'advance':
                return $this->editService->createOrUpdateAdvance($payrollId, $data);
        }

        if ($result) {
            $this->editService->regenerateAffectedPayments($payrollId, $biweekly);
        }

        return ['status' => 201, 'message' => ucfirst($type) . ' agregado', 'data' => $result];
    }

    public function editPayment($payrollId, $type, $paymentId, $data, $biweekly = null)
    {
        $canEdit = $this->editService->canEditPayroll($payrollId, $biweekly);
        if (!$canEdit['can_edit']) return ['status' => 400, 'message' => $canEdit['reason']];

        $model = null;
        switch ($type) {
            case 'additional':
                $model = AdditionalPayment::find($paymentId);
                break;
            case 'discount':
                $model = DiscountPayment::find($paymentId);
                break;
            case 'loan':
                $model = \App\Models\Loan::find($paymentId);
                break;
            case 'advance':
                return $this->editService->createOrUpdateAdvance($payrollId, $data, $paymentId);
        }

        if (!$model) return ['status' => 404, 'message' => 'Pago no encontrado'];

        $model->update($data);
        $this->editService->regenerateAffectedPayments($payrollId, $biweekly);

        return ['status' => 200, 'message' => ucfirst($type) . ' actualizado', 'data' => $model];
    }

    public function deletePayment($payrollId, $type, $paymentId, $biweekly = null)
    {
        $canEdit = $this->editService->canEditPayroll($payrollId, $biweekly);
        if (!$canEdit['can_edit']) return ['status' => 400, 'message' => $canEdit['reason']];

        $model = null;
        switch ($type) {
            case 'additional':
                $model = AdditionalPayment::find($paymentId);
                break;
            case 'discount':
                $model = DiscountPayment::find($paymentId);
                break;
            case 'loan':
                $model = \App\Models\Loan::find($paymentId);
                break;
        }

        if (!$model) return ['status' => 404, 'message' => 'Pago no encontrado'];

        $model->delete();
        $this->editService->regenerateAffectedPayments($payrollId, $biweekly);

        return ['status' => 200, 'message' => ucfirst($type) . ' eliminado'];
    }

    public function reopenPayroll($payrollId)
    {
        try {
            $payroll = PayRoll::find($payrollId);
            if (!$payroll) return ['status' => 404, 'message' => 'Planilla no encontrada'];

            $payroll->update(['status' => PayRoll::STATUS_OPEN]);

            return ['status' => 200, 'message' => 'Planilla reabierta', 'data' => $payroll];
        } catch (\Exception $e) {
            return ['status' => 500, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    // En PayRollService.php
    public function getMaxAdvance($payrollId, $biweekly, $payCard = null)
    {
        try {
            $maxAmount = $this->editService->calculateMaxAdvance($payrollId, $biweekly, $payCard);

            return [
                'status' => 200,
                'max_amount' => $maxAmount,
                'currency' => 'PEN',
                'payroll_id' => $payrollId,
                'biweekly' => $biweekly,
                'pay_card' => $payCard
            ];
        } catch (\Exception $e) {
            Log::error("Error en getMaxAdvance: " . $e->__toString());
            return [
                'status' => 500,
                'message' => 'Error calculando máximo adelanto',
                'max_amount' => 0
            ];
        }
    }
}
