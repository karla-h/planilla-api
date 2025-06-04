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
        return response()->json($this->service->findAll(), 200);
    }

    public function store(AffiliationRequest $request): JsonResponse
    {
        $affiliation = $this->service->create($request);
        return response()->json($affiliation, $affiliation['status']);
    }

    public function show(string $key): JsonResponse
    {
        try {
            $affiliation = $this->service->findBy($key);
            return response()->json($affiliation, 200);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function update(AffiliationRequest $request, string $key): JsonResponse
    {
        try {
            $affiliation = $this->service->edit($key, $request);
            return response()->json($affiliation, 200);
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
