<?php

namespace App\Http\Controllers;

use App\Architecture\Application\Services\DiscountTypeService;
use App\Exceptions\EntityNotFoundException;
use App\Http\Requests\DiscountTypeRequest;
use Illuminate\Http\JsonResponse;

class DiscountTypeController extends Controller
{
    public function __construct(protected DiscountTypeService $service) {}

    public function index(): JsonResponse
    {
        try {
            $discountTypes = $this->service->findAll();
            return response()->json($discountTypes, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener tipos de descuento'
            ], 500);
        }
    }

    public function store(DiscountTypeRequest $request): JsonResponse
    {
        try {
            $discountType = $this->service->create($request);
            return response()->json($discountType, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear tipo de descuento'
            ], 500);
        }
    }

    public function show(string $key): JsonResponse
    {
        try {
            $discountType = $this->service->findBy($key);
            return response()->json($discountType, 200);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener tipo de descuento'
            ], 500);
        }
    }

    public function update(DiscountTypeRequest $request, string $key): JsonResponse
    {
        try {
            $discountType = $this->service->edit($key, $request);
            return response()->json($discountType, 200);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar tipo de descuento'
            ], 500);
        }
    }

    public function destroy(string $key): JsonResponse
    {
        try {
            $result = $this->service->delete($key);
            return response()->json($result, 202);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar tipo de descuento'
            ], 500);
        }
    }
}