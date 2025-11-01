<?php

namespace App\Http\Controllers;

use App\Models\AdditionalPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdditionalPaymentController extends Controller
{
    public function index()
    {
        try {
            $additionalPayments = AdditionalPayment::all();
            return response()->json($additionalPayments);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener pagos adicionales'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'pay_roll_id' => 'required|exists:pay_rolls,id',
                'payment_type_id' => 'required|exists:payment_types,id',
                'amount' => 'required|numeric|min:0',
                'quantity' => 'sometimes|integer|min:1',
                'biweek' => 'sometimes|integer|in:1,2',
                'pay_card' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $data = [
                'pay_roll_id' => $request->input('pay_roll_id'),
                'payment_type_id' => $request->input('payment_type_id'),
                'amount' => $request->input('amount'),
                'quantity' => $request->input('quantity', 1),
                'biweek' => $request->input('biweek', 1),
                'pay_card' => $request->input('pay_card', false)
            ];

            $additionalPayment = AdditionalPayment::create($data);
            return response()->json($additionalPayment, 201);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear pago adicional'], 500);
        }
    }

    public function show($id)
    {
        try {
            $additionalPayment = AdditionalPayment::find($id);
            return $additionalPayment 
                ? response()->json($additionalPayment) 
                : response()->json(['message' => 'No encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener pago adicional'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $additionalPayment = AdditionalPayment::find($id);
            if (!$additionalPayment) {
                return response()->json(['message' => 'No encontrado'], 404);
            }

            $validator = Validator::make($request->all(), [
                'amount' => 'sometimes|required|numeric|min:0',
                'quantity' => 'sometimes|integer|min:1',
                'biweek' => 'sometimes|integer|in:1,2',
                'pay_card' => 'sometimes|boolean',
                'status_code' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $additionalPayment->update($validator->validated());
            return response()->json($additionalPayment);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar pago adicional'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $additionalPayment = AdditionalPayment::find($id);
            if (!$additionalPayment) {
                return response()->json(['message' => 'No encontrado'], 404);
            }

            $additionalPayment->delete();
            return response()->json(['message' => 'Eliminado correctamente']);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar pago adicional'], 500);
        }
    }
}