<?php

namespace App\Http\Controllers;

use App\Architecture\Application\Services\PaymentTypeService;
use App\Exceptions\EntityNotFoundException;
use App\Http\Requests\PaymentTypeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentTypeController extends Controller
{
    public function __construct(protected PaymentTypeService $service) {}

    public function index(): JsonResponse
    {
        return response()->json($this->service->findAll(), 200);
    }

    public function store(PaymentTypeRequest $request): JsonResponse
    {
        $paymentType = $this->service->create($request);
        return response()->json($paymentType, 201);
    }

    public function show(string $key): JsonResponse
    {
        try {
            $paymentType = $this->service->findBy($key);
            return response()->json($paymentType, 200);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function update(PaymentTypeRequest $request, string $key): JsonResponse
    {
        try {
            $paymentType = $this->service->edit($key, $request);
            return response()->json($paymentType, 200);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function destroy(string $key): JsonResponse
    {
        try {
            return response()->json($this->service->delete($key), 202);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }
}
