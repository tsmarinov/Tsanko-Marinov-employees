<?php

namespace App\Http\Controllers;

use App\Services\Employees\EmployeePairingPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeePairController extends Controller
{
    public function show(): View
    {
        return view('employee-pairs.index');
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $result = (new EmployeePairingPipeline())->run($request->file('csv')->getRealPath());

        return response()->json($result);
    }
}
