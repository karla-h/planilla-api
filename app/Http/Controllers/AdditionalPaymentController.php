<?php

namespace App\Http\Controllers;

use App\Models\AdditionalPayment;
use Illuminate\Http\Request;

class AdditionalPaymentController extends Controller
{
    public function index()
    {
        return response()->json(AdditionalPayment::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric',
            'status_code' => 'nullable|string|max:20',
        ]);

        $additionalPayment = AdditionalPayment::create($validated);

        return response()->json($additionalPayment, 201);
    }

    public function show($id)
    {
        $additionalPayment = AdditionalPayment::find($id);
        return $additionalPayment ? response()->json($additionalPayment) : response()->json(['message' => 'Not found'], 404);
    }

    public function update(Request $request, $id)
    {
        $additionalPayment = AdditionalPayment::find($id);
        if (!$additionalPayment) return response()->json(['message' => 'Not found'], 404);

        $validated = $request->validate([
            'amount' => 'sometimes|required|numeric',
            'status_code' => 'nullable|string|max:20',
        ]);

        $additionalPayment->update($validated);
        return response()->json($additionalPayment);
    }

    public function destroy($id)
    {
        $additionalPayment = AdditionalPayment::find($id);
        if (!$additionalPayment) return response()->json(['message' => 'Not found'], 404);

        $additionalPayment->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}