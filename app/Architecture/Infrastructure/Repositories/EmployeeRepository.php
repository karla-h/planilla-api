<?php

namespace App\Architecture\Infrastructure\Repositories;

use App\Architecture\Domain\Models\Entities\AffiliationData;
use App\Architecture\Domain\Models\Entities\EmployeeData;
use App\Exceptions\EntityNotFoundException;
use App\Http\Requests\ContractRequest;
use App\Models\Affiliation;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\Headquarter;
use Illuminate\Support\Facades\DB;

class EmployeeRepository implements IBaseRepository
{

    public function __construct(protected ContractRepository $contractRepository) {}

    public function create($data)
    {
        try {
            return DB::transaction(function () use ($data) {
                $headquarter = Headquarter::where('name', $data['headquarter']['name'])->first();

                if (!$headquarter) {
                    throw new EntityNotFoundException('Headquarter not found');
                }

                $data['headquarter_id'] = $headquarter->id;

                $employee = Employee::create($data);

                if (isset($data['contract'])) {
                    Contract::create([
                        'employee_id' => $employee->id,
                        'accounting_salary' => $data['contract']['accounting_salary'] ?? null,
                        'real_salary' => $data['contract']['real_salary'] ?? null,
                        'hire_date' => $data['contract']['hire_date'] ?? null,
                        'termination_date' => $data['contract']['termination_date'] ?? null,
                        'termination_reason' => $data['contract']['termination_reason'] ?? null,
                    ]);
                }

                if (isset($data['affiliations'])) {
                    foreach ($data['affiliations'] as $affiliationData) {
                        $affiliation = Affiliation::where('description', $affiliationData['description'])->first();

                        if ($affiliation) {
                            $percent = isset($affiliationData['percent']) && $affiliationData['percent'] !== 0
                                ? $affiliationData['percent']
                                : $affiliation->percent;

                            EmployeeAffiliation::create([
                                'employee_id' => $employee->id,
                                'affiliation_id' => $affiliation->id,
                                'percent' => $percent,
                            ]);
                        }
                    }
                }

                return [
                    'message' => 'Employee created successfully',
                    'affiliations' => $data['affiliations'],
                    'extra' => ['extraa' => $data['contract']],
                    'data' => EmployeeData::optional($employee),
                    'status' => 201
                ];
            });
        } catch (\Throwable $th) {
            return ['message' => 'Error, data cannot be processed: ' . $th->getMessage(), 'status' => 500];
        }
    }

    public function edit($dni, $data)
    {
        try {
            $employee = Employee::where('dni', $dni)->first();

            if (!$employee) {
                throw new EntityNotFoundException('Employee not found');
            }

            $headquarter = Headquarter::where('name', $data['headquarter']['name'])->first();
            if (!$headquarter) {
                throw new EntityNotFoundException('Headquarter not found');
            }

            $data['headquarter_id'] = $headquarter->id;
            $employee->update($data);

            $affi = $data['affiliations'] ?? [];
            $employee->employeeAffiliations()->delete();

            foreach ($affi as $affiliationData) {
                $affiliation = Affiliation::where('description', $affiliationData['description'])->first();

                if ($affiliation) {
                    EmployeeAffiliation::create([
                        'employee_id' => $employee->id,
                        'affiliation_id' => $affiliation->id,
                        'percent' => $affiliationData['percent'] ?? $affiliation->percent,
                    ]);
                }
            }

            if (isset($data['contract'])) {
                $employee->activeContract()->update([
                    'hire_date' => $data['contract']['hire_date'] ?? null,
                    'accounting_salary' => $data['contract']['accounting_salary'] ?? null,
                    'real_salary' => $data['contract']['real_salary'] ?? null,
                    'termination_date' => $data['contract']['termination_date'] ?? null,
                    'termination_reason' => $data['contract']['termination_reason'] ?? null,
                ]);
            }

            return ['message' => 'Employee updated successfully', 'status' => 200];
        } catch (EntityNotFoundException $e) {
            return ['message' => $e->getMessage(), 'status' => 404];
        } catch (\Exception $e) {
            return ['message' => 'An error occurred: ' . $e->getMessage(), 'status' => 500];
        }
    }


    public function findBy($dni)
    {
        $employee = Employee::where('dni', $dni)
            ->with(['headquarter', 'employeeAffiliations'])
            ->first();

        if (!$employee) {
            throw new EntityNotFoundException('Employee not found');
        }

        $affiliations = $employee->employeeAffiliations->map(function ($employeeAffiliation) {
            return AffiliationData::from(
                [
                    'description' => $employeeAffiliation->affiliation->description,
                    'percent' => $employeeAffiliation->percent,
                ]
            );
        });

        $contract = $employee->activeContract();
        return EmployeeData::optional(
            [
                'firstname' => $employee->firstname,
                'lastname' => $employee->lastname,
                'dni' => $employee->dni,
                'born_date' => $employee->born_date,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'account' => $employee->account,
                'address' => $employee->address,
                'department' => $employee->department,
                'headquarter' => $employee->headquarter,
                'affiliations' => $affiliations,
                'contract' => $contract,
            ]
        );
    }

    public function findAll()
    {
        return EmployeeData::collect(Employee::with(['headquarter'])->get());
    }

    public function delete($dni)
    {
        $employee = Employee::where('dni', $dni)->first();

        if (!$employee) {
            throw new EntityNotFoundException('Employee not found');
        }

        $employee->delete();
        return ['message' => 'Employee deleted successfully', 'status' => 202];
    }

    public function getEmployeesWithoutPayroll()
    {
        try {
            $currentYear = now()->year;
            $currentMonth = now()->month;

            $employeesWithoutPayroll = Employee::whereDoesntHave('payRolls', function ($query) use ($currentYear, $currentMonth) {
                $query->whereYear('created_at', $currentYear)
                    ->whereMonth('created_at', $currentMonth);
            })->with('headquarter:id,name')
                ->select('dni', 'firstname', 'lastname', 'headquarter_id')
                ->get();

            if ($employeesWithoutPayroll->isEmpty()) {
                return ['message' => 'All employees have payroll for this month', 'status' => 200];
            }

            return ['employees' => $employeesWithoutPayroll, 'status' => 200];
        } catch (\Throwable $th) {
            return ['message' => 'Error retrieving employees: ' . $th->getMessage(), 'status' => 500];
        }
    }

    public function getEmployeesByBirthday($date)
    {
        try {
            
            $month = date('m', strtotime($date));
            $day = date('d', strtotime($date));

            $employees = Employee::whereMonth('born_date', $month)
                ->whereDay('born_date', $day)
                ->select('dni', 'firstname', 'lastname', 'born_date')
                ->get();

            if ($employees->isEmpty()) {
                return ['message' => 'No employees have a birthday on this date', 'status' => 200];
            }

            return ['employees' => $employees, 'status' => 200];
        } catch (\Throwable $th) {
            return ['message' => 'Error retrieving employees: ' . $th->getMessage(), 'status' => 500];
        }
    }
}
