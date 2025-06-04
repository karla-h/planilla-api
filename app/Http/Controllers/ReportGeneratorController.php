<?php

namespace App\Http\Controllers;

use App\Architecture\Application\Services\ReportGenerator;
use Illuminate\Http\Request;

class ReportGeneratorController extends Controller
{
    public function __construct(protected ReportGenerator $service) {}

    public function generatePayRolls(Request $request)
    {
        $headquarter = $request->query('headquarter', 'all');
        $pay_date = $request->query('pay_date', now());

        $data = $this->service->generatePayRolls($headquarter, $pay_date);

        return response()->json($data, 200);
    }
}