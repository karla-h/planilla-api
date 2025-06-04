<?php

namespace App\Http\Controllers;

use App\Architecture\Application\Services\BiweeklyPaymentService;
use Illuminate\Http\Request;

class BiweeklyPaymentController extends Controller
{
    public function __construct(protected BiweeklyPaymentService $service) {}

    public function store(Request $request)
    {
        $biweeklyPayment = $this->service->create($request->all());

        return response()->json($biweeklyPayment, $biweeklyPayment['status']);
    }

    public function reportByBiweekly(Request $request) {
        return $this->service->reportByBiweekly($request);
    }
}