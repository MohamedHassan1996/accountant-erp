<?php

namespace App\Http\Controllers\Api\Private\Client;

use App\Http\Controllers\Controller;
use App\Models\Client\Client;
use App\Models\Parameter\ParameterValue;
use Carbon\Carbon;
use Illuminate\Http\Request;


class ClientPayInstallmentEndDateController extends Controller
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

        $client = Client::find($request->clientId);

        $startAt = Carbon::parse($request->startAt);

        $allowedDaysToPay = $client->allowed_days_to_pay ?? 0; // Fetch from the client table

        $installmentEndDataAdd = ParameterValue::where('id', $request->paymentTypeId)->first();

        $installmentEndDataAddMonth = ceil($installmentEndDataAdd->description / 30);

        $endDate = $startAt->copy()->addMonths($installmentEndDataAddMonth)->endOfMonth();

        $isSpecialMonthEnd = in_array($endDate->format('m-d'), ['08-31', '12-31']);

        if ($isSpecialMonthEnd && $allowedDaysToPay == 0) {
            $endDate->addDays(10);
        } else {
            $endDate->addDays($allowedDaysToPay);
        }

        return response()->json([
            'endAt' => $endDate->format('Y-m-d'),
        ]);

    }


}
