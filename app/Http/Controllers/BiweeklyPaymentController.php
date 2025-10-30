<?php

namespace App\Http\Controllers;

use App\Architecture\Application\Services\BiweeklyPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BiweeklyPaymentController extends Controller
{
    public function __construct(protected BiweeklyPaymentService $service) {}

    public function store(Request $request): JsonResponse
    {
        $biweeklyPayment = $this->service->create($request->all());
        return response()->json($biweeklyPayment, $biweeklyPayment['status']);
    }

    public function createForAllEmployees(): JsonResponse
    {
        $result = $this->service->createForAllEmployees();
        return response()->json($result, 200);
    }

    public function reportByBiweekly(Request $request): JsonResponse
    {
        $result = $this->service->reportByBiweekly($request);
        return $result;
    }
}