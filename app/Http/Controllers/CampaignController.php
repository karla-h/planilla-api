<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Employee;
use App\Models\PayRoll;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    private function campaignValidation($request)
    {
        return $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'biweek' => 'required|integer',
        ]);
    }

    public function store(Request $request)
    {
        try {
            $this->campaignValidation($request);

            $campaign = Campaign::create([
                'description' => $request->description,
                'amount' => $request->amount,
                'biweek' => $request->biweek,
            ]);

            return response()->json($campaign, 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to create campaign', 'message' => $e->getMessage()], 500);
        }
    }

    public function index()
    {
        try {
            $campaigns = Campaign::all();
            return response()->json($campaigns);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to fetch campaigns', 'message' => $e->getMessage()], 500);
        }
    }

    public function show($description)
    {
        try {
            $campaign = Campaign::where('description', $description)->first();

            if (!$campaign) {
                throw new ModelNotFoundException("Error Processing Request", 404);
            }

            return response()->json($campaign);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Campaign not found', 'message' => $e->getMessage(), 'status' => 404], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to fetch campaign', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $this->campaignValidation($request);

            $campaign = Campaign::where('description', $id)->first();

            if (!$campaign) {
                return response()->json(['message' => 'Campaign not found'], 404);
            }

            $campaign->update([
                'description' => $request->description,
                'amount' => $request->amount,
                'biweek' => $request->biweek,
            ]);

            return response()->json($campaign);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to update campaign', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $campaign = Campaign::where('description', $id)->first();

            if (!$campaign) {
                return response()->json(['message' => 'Campaign not found'], 404);
            }

            $campaign->delete();

            return response()->json(['message' => 'Campaign deleted successfully']);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to delete campaign', 'message' => $e->getMessage()], 500);
        }
    }

    public function campaignForPayrolls(Request $request)
    {
        try {
            $employees = $request->input('employees');

            if (empty($employees)) {
                return response()->json(['message' => 'Error, data is empty '], 200);
            }

            $validatedData = $request->validate([
                'description' => 'required|string',
                'employees' => 'required|array',
                'employees.*.dni' => 'required|string',
            ]);

            $currentYear = now()->year;
            $currentMonth = now()->month;

            $campaign = Campaign::where('description', $validatedData['description'])->first();
            foreach ($validatedData['employees'] as $data) {
                $emp = Employee::where('dni', $data['dni'])->first();
                $pay = PayRoll::where('employee_id', $emp->id)
                    ->whereYear('created_at', $currentYear)
                    ->whereMonth('created_at', $currentMonth)
                    ->first();
                $pay->campaign_id = $campaign->id;
                $pay->save();
            }

            return response()->json(['message' => 'Campaign created/updated successfully'], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to process request', 'message' => $e], 500);
        }
    }
}
