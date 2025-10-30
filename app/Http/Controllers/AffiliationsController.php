<?php

namespace App\Http\Controllers;

use App\Architecture\Application\Services\AffiliationService;
use App\Exceptions\EntityNotFoundException;
use App\Http\Requests\AffiliationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AffiliationsController extends Controller
{
    public function __construct(protected AffiliationService $service) {}

    public function index(): JsonResponse
    {
        try {
            Log::info('Obteniendo todas las afiliaciones');
            $affiliations = $this->service->findAll();
            return response()->json($affiliations, 200);
        } catch (\Exception $e) {
            Log::error('Error en AffiliationsController@index: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function store(AffiliationRequest $request): JsonResponse
    {
        try {
            Log::info('Creando nueva afiliación', $request->all());
            $response = $this->service->create($request->validated());
            return response()->json($response, $response['status']);
        } catch (\Exception $e) {
            Log::error('Error en AffiliationsController@store: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al crear afiliación: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            Log::info('Buscando afiliación ID: ' . $id);
            $affiliation = $this->service->findBy($id);
            return response()->json($affiliation, 200);
        } catch (EntityNotFoundException $e) {
            Log::warning('Afiliación no encontrada: ' . $id);
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Error en AffiliationsController@show: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener afiliación'
            ], 500);
        }
    }

    public function update(AffiliationRequest $request, string $id): JsonResponse
    {
        try {
            Log::info('Actualizando afiliación ID: ' . $id, $request->all());
            $response = $this->service->edit($id, $request->validated());
            return response()->json($response, $response['status']);
        } catch (EntityNotFoundException $e) {
            Log::warning('Afiliación no encontrada para actualizar: ' . $id);
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Error en AffiliationsController@update: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al actualizar afiliación: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            Log::info('Eliminando afiliación ID: ' . $id);
            $response = $this->service->delete($id);
            return response()->json($response, $response['status']);
        } catch (EntityNotFoundException $e) {
            Log::warning('Afiliación no encontrada para eliminar: ' . $id);
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error('Error en AffiliationsController@destroy: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al eliminar afiliación'
            ], 500);
        }
    }
}