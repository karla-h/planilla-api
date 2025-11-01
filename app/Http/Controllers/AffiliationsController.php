<?php

namespace App\Http\Controllers;

use App\Architecture\Application\Services\AffiliationService;
use App\Exceptions\EntityNotFoundException;
use App\Http\Requests\AffiliationRequest;
use Illuminate\Http\JsonResponse;

class AffiliationsController extends Controller
{
    public function __construct(protected AffiliationService $service) {}

    public function index(): JsonResponse
    {
        try {
            $affiliations = $this->service->findAll();
            return response()->json($affiliations, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function store(AffiliationRequest $request): JsonResponse
    {
        try {
            $response = $this->service->create($request->validated());
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear afiliación'
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $affiliation = $this->service->findBy($id);
            return response()->json($affiliation, 200);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener afiliación'
            ], 500);
        }
    }

    public function update(AffiliationRequest $request, string $id): JsonResponse
    {
        try {
            $response = $this->service->edit($id, $request->validated());
            return response()->json($response, $response['status']);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar afiliación'
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $response = $this->service->delete($id);
            return response()->json($response, $response['status']);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar afiliación'
            ], 500);
        }
    }
}