<?php

namespace App\Http\Controllers;

use App\Architecture\Application\Services\HeadquarterService;
use App\Exceptions\EntityNotFoundException;
use App\Http\Requests\HeadquarterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeadquarterController extends Controller
{
    public function __construct(protected HeadquarterService $headquarterService) {}

    public function index(): JsonResponse
    {
        return response()->json($this->headquarterService->findAll());
    }

    public function store(HeadquarterRequest $request): JsonResponse
    {
        $headquarter = $this->headquarterService->create($request);
        return response()->json($headquarter, 201);
    }

    public function show(string $id): JsonResponse
    {
        try {
            $headquarter = $this->headquarterService->findBy($id);
            return response()->json($headquarter);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function update(HeadquarterRequest $request, string $id): JsonResponse
    {
        try {
            $headquarter = $this->headquarterService->edit($id, $request);
            return response()->json($headquarter, 200);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function destroy(string $name): JsonResponse
    {
        try {
            return response()->json($this->headquarterService->delete($name), 202);
        } catch (EntityNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }
}