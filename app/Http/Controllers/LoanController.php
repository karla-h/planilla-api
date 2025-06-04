<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Http\Requests\StoreLoanRequest;
use App\Http\Requests\UpdateLoanRequest;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $loans = Loan::all();
            return response()->json(['success' => true, 'data' => $loans], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error fetching loans', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a new loan.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'employee' => 'required|string|exists:employees,dni',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'amount' => 'required|numeric|min:0',
                'pay_card' => 'required|integer|in:1,2',
                'biweek' => 'required|integer|in:1,2'
            ]);

            $existingLoan = Loan::where('employee', $request->employee)->exists();
            if ($existingLoan) {
                return response()->json(['success' => false, 'message' => 'The employee already has an active loan'], 409);
            }

            $loan = Loan::create($request->all());
            
            return response()->json(['success' => true, 'data' => $loan], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error creating loan', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display loan by employee (DNI).
     */
    public function show($employee)
    {
        try {
            $loan = Loan::where('employee', $employee)
            ->select('employee', 'start_date', 'end_date', 'amount', 'pay_card', 'biweek')
            ->firstOrFail();

            return response()->json([$loan], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Loan not found for this employee'], 404);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error fetching loan', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update loan by employee (DNI).
     */
    public function update(Request $request, $employee)
    {
        try {
            $request->validate([
                'start_date' => 'sometimes|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'amount' => 'sometimes|numeric|min:0',
                'pay_card' => 'sometimes|integer|in:1,2',
                'biweek' => 'required|integer|in:1,2'
            ]);

            $loan = Loan::where('employee', $employee)->firstOrFail();
            $loan->update($request->all());

            return response()->json(['success' => true, 'data' => $loan], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Loan not found for this employee'], 404);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error updating loan', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete loan by employee (DNI) - Soft delete.
     */
    public function destroy($employee)
    {
        try {
            $loan = Loan::where('employee', $employee)->firstOrFail();
            $loan->delete();

            return response()->json(['success' => true, 'message' => 'Loan deleted successfully'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Loan not found for this employee'], 404);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error deleting loan', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Restore a soft-deleted loan by employee (DNI).
     */
    public function restore($employee)
    {
        try {
            $loan = Loan::onlyTrashed()->where('employee', $employee)->firstOrFail();
            $loan->restore();

            return response()->json(['success' => true, 'message' => 'Loan restored successfully'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Loan not found in trash for this employee'], 404);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error restoring loan', 'error' => $e->getMessage()], 500);
        }
    }
}
