<?php

namespace App\Http\Controllers;

use App\Models\DiscountPayment;
use Illuminate\Http\Request;

class DiscountPaymentController extends Controller
{
    public function index()
    {
        return response()->json(DiscountPayment::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'payroll_id' => 'required|exists:pay_rolls,id',
            'discount_type_id' => 'required|exists:discount_types,id',
            'amount' => 'required|numeric',
            'status_code' => 'nullable|string|max:20',
        ]);

        $discountPayment = DiscountPayment::create($validated);

        return response()->json($discountPayment, 201);
    }

    public function show($id)
    {
        $discountPayment = DiscountPayment::find($id);
        return $discountPayment ? response()->json($discountPayment) : response()->json(['message' => 'Not found'], 404);
    }

    public function update(Request $request, $id)
    {
        $discountPayment = DiscountPayment::find($id);
        if (!$discountPayment) return response()->json(['message' => 'Not found'], 404);

        $validated = $request->validate([
            'payroll_id' => 'sometimes|required|exists:pay_rolls,id',
            'discount_type_id' => 'sometimes|required|exists:discount_types,id',
            'amount' => 'sometimes|required|numeric',
            'status_code' => 'nullable|string|max:20',
        ]);

        $discountPayment->update($validated);
        return response()->json($discountPayment);
    }

    public function destroy($id)
    {
        $discountPayment = DiscountPayment::find($id);
        if (!$discountPayment) return response()->json(['message' => 'Not found'], 404);

        $discountPayment->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
