<?php

namespace App\Http\Controllers\Api\Private\Client;

use App\Http\Controllers\Controller;
use App\Models\Client\Client;
use App\Models\Parameter\ParameterValue;
use Illuminate\Http\Request;


class ClientPayInstallmentDividerController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:api');
        // $this->middleware('permission:all_client_pay_installments', ['only' => ['index']]);
        // $this->middleware('permission:create_client_pay_installment', ['only' => ['create']]);
        // $this->middleware('permission:edit_client_pay_installment', ['only' => ['edit']]);
        // $this->middleware('permission:update_client_pay_installment', ['only' => ['update']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $installmentNumbers = ParameterValue::where('id', $request->payStepsId)->pluck('description')->first();

        $client = Client::find($request->clientId);

        $installmentAmount = 0;
        if ($client) {
            $installmentAmount = $client->price / $installmentNumbers;
        }


        $allowedDaysToPay = $client->allowed_days_to_pay ?? 0; // Fetch from the client table

        $installmentsData = [];
        $currentDate = now()->startOfMonth(); // First day of the current month


        foreach ( range(1, $installmentNumbers) as $installmentNumber ) {
            $installmentsData[] = [
                'startAt' => $currentDate->toDateString(),
                'endAt' => $currentDate->copy()->addDays($allowedDaysToPay)->toDateString(),
                'description' => '',
                'amount' => round($installmentAmount, 2),
                'payInstallmentSubData' => []
            ];

            $currentDate->addMonth(); // Move to the next month
        }

        return response()->json([
            'data' => [
                'payInstallments' => $installmentsData
            ]
        ]);

    }


}
