<?php

namespace App\Http\Controllers;

use App\Architecture\Application\Services\EmployeeService;
use App\Exceptions\EntityNotFoundException;
use App\Http\Requests\EmployeeRequest;
use Illuminate\Http\JsonResponse;

class EmployeeController extends Controller
{

    public function __construct(protected EmployeeService $service) {}

    public function index(): JsonResponse
    {
        return response()->json($this->service->findAll(), 200);
    }

    public function store(EmployeeRequest $request): JsonResponse
    {
        $response = $this->service->create($request);
        return response()->json($response, $response['status']);
    }

    public function show(string $dni): JsonResponse
    {
        try {
            return response()->json($this->service->findBy($dni), 200);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function update(EmployeeRequest $request, string $dni): JsonResponse
    {
        try {
            $response = $this->service->edit($dni, $request);
            return response()->json(['message' => $response['message']], $response['status']);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function destroy(string $dni): JsonResponse
    {
        try {
            $response = $this->service->delete($dni);
            return response()->json(['message' => $response['message']], $response['status']);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function getEmployeesWithoutPayroll() {
        $response = $this->service->getEmployeesWithoutPayroll();
        return response()->json($response, $response['status']);
    }

    public function getEmployeesByBirthday(string $birth) {
        $response = $this->service->getEmployeesByBirthday($birth);
        return response()->json($response, $response['status']);
    }
}
