<?php

namespace App\Http\Controllers;

use App\Models\AdditionalPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdditionalPaymentController extends Controller
{
    public function index()
    {
        return response()->json(AdditionalPayment::all());
    }

    // En AdditionalPaymentController.php
public function store(Request $request)
{
    Log::info('=== ADDITIONAL PAYMENT STORE ===', $request->all());
    
    try {
        $data = [
            'pay_roll_id' => $request->input('pay_roll_id'),
            'payment_type_id' => $request->input('payment_type_id'),
            'amount' => $request->input('amount'),
            'quantity' => $request->input('quantity', 1),
            'biweek' => $request->input('biweek', 1),
            'pay_card' => $request->input('pay_card', false)
        ];

        Log::info('Datos para crear:', $data);

        $additionalPayment = AdditionalPayment::create($data);
        
        Log::info('AdditionalPayment creado:', $additionalPayment->toArray());
        
        return response()->json($additionalPayment, 201);
        
    } catch (\Exception $e) {
        Log::error('Error creando AdditionalPayment:', [
            'error' => $e->getMessage(),
            'request' => $request->all()
        ]);
        return response()->json(['error' => $e->getMessage()], 500);
    }
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