<?php

namespace App\Http\Controllers;

use App\Models\Extra;
use Exception;
use Illuminate\Http\Request;

class ExtraController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $employeeDni = $request->input('employee');
            $extras = $request->input('extras');

            if (empty($extras)) {
                Extra::where('employee', $employeeDni)->delete();
                return response()->json(['message' => 'All extras for employee removed successfully'], 200);
            }

            $validatedData = $request->validate([
                'employee' => 'required|string',
                'extras' => 'required|array',
                'extras.*.employee' => 'required|string',
                'extras.*.description' => 'required|string|max:255',
                'extras.*.amount' => 'required|numeric|min:0',
                'extras.*.quantity' => 'required|integer|min:1',
                'extras.*.apply_date' => 'required|date',
            ]);

            $createdOrUpdatedExtras = [];
            foreach ($validatedData['extras'] as $extraData) {
                $extra = Extra::where('employee', $employeeDni)
                    ->where('description', $extraData['description'])
                    ->where('apply_date', $extraData['apply_date'])
                    ->first();

                if ($extra) {
                    $extra->update($extraData);
                } else {
                    $extra = Extra::create($extraData);
                }
                $createdOrUpdatedExtras[] = $extra;
            }

            return response()->json(['message' => 'Extras created/updated successfully'], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to process request', 'message' => $e], 500);
        }
    }



    /**
     * Display the specified resource.
     */
    public function show($dni)
    {
        $response = Extra::where('employee', $dni)
            ->select('employee', 'description', 'apply_date', 'amount', 'quantity')    
        ->get();
        return $response;
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Extra $extra)
    {
        try {
            $validated = $request->validate([
                'description' => 'sometimes|required|string|max:255',
                'employee' => 'sometimes|required|string|exists:employees,dni',
                'amount' => 'sometimes|required|numeric|min:0',
                'quantity' => 'sometimes|required|integer|min:1',
                'apply_date' => 'sometimes|required|date',
            ]);

            $extra = Extra::where('employee', $extra->employee)
                ->where('description', $extra->description)
                ->first();
            $extra->update($validated);

            return response()->json(['message' => 'Extra updated successfully', 'data' => $extra], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to update transaction', 'message' => $e], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Extra $extra)
    {
        //
    }
}
