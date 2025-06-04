<?php

namespace App\Http\Controllers;

use App\Architecture\Application\Services\PayRollService;
use App\Http\Requests\PayRollRequest;
use App\Models\PayRoll;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayRollController extends Controller
{

    public function __construct(protected PayRollService $service) {}


    public function index(Request $request): JsonResponse
    {
        $year = $request->filled('year') ? $request->input('year') : now()->year;
        $month = $request->filled('month') ? $request->input('month') : now()->month;
        $request = ['year' => $year, 'month' => $month];
        return response()->json($this->service->findAll($request), 200);
    }

    public function store(PayRollRequest $request): JsonResponse
    {
        $payroll = $this->service->create($request);
        return response()->json($payroll, $payroll['status']);
    }

    public function show($dni)
    {
        $request = $this->service->findBy($dni);
        return response()->json($request, $request['status']);
    }

    public function update(PayRollRequest $request, $id) {
        $payroll = $this->service->edit($id, $request);
        return response()->json($payroll, $payroll['status']);
    }

    public function destroy($id)
    {
    }

    public function createForAllEmployees() {
        $response = $this->service->createForAllEmployees();
        return response()->json($response, $response['status']);
    }

    public function findByEmployeeAndPaydate(Request $request) { 
        $response = $this->service->findByEmployeeAndPaydate($request['dni'], $request['pay_date']);
        return $response; 
    }

    public function createPayrollsForSpecificEmployees(Request $request) {
        return $this->service->createPayrollsForSpecificEmployees($request);
    }
}
