<?php

namespace App\Http\Controllers;

use App\Models\DiscountPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DiscountPaymentController extends Controller
{
    public function index()
    {
        try {
            $discountPayments = DiscountPayment::all();
            return response()->json($discountPayments);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener descuentos'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'pay_roll_id' => 'required|exists:pay_rolls,id',
                'discount_type_id' => 'required|exists:discount_types,id',
                'amount' => 'required|numeric',
                'quantity' => 'sometimes|numeric',
                'biweek' => 'sometimes|numeric|nullable',
                'pay_card' => 'sometimes|in:0,1'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $discountPayment = DiscountPayment::create($validator->validated());
            return response()->json($discountPayment, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear descuento'], 500);
        }
    }

    public function show($id)
    {
        try {
            $discountPayment = DiscountPayment::find($id);
            return $discountPayment 
                ? response()->json($discountPayment) 
                : response()->json(['message' => 'No encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener descuento'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $discountPayment = DiscountPayment::find($id);
            if (!$discountPayment) {
                return response()->json(['message' => 'No encontrado'], 404);
            }

            $validator = Validator::make($request->all(), [
                'pay_roll_id' => 'sometimes|required|exists:pay_rolls,id',
                'discount_type_id' => 'sometimes|required|exists:discount_types,id',
                'amount' => 'sometimes|required|numeric',
                'quantity' => 'sometimes|numeric',
                'biweek' => 'sometimes|numeric|nullable',
                'pay_card' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $discountPayment->update($validator->validated());
            return response()->json($discountPayment);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar descuento'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $discountPayment = DiscountPayment::find($id);
            if (!$discountPayment) {
                return response()->json(['message' => 'No encontrado'], 404);
            }

            $discountPayment->delete();
            return response()->json(['message' => 'Eliminado correctamente']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar descuento'], 500);
        }
    }
}