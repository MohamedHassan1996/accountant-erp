<?php

namespace App\Http\Controllers\Api\Private\Client;

use App\Http\Controllers\Controller;

use App\Models\Client\Client;
use App\Models\Parameter\ParameterValue;
use Carbon\Carbon;
use Illuminate\Http\Request;


class ClientPaymentPeriodController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('permission:client_payment_period', ['only' => ['index']]);
        // $this->middleware('permission:create_client', ['only' => ['create']]);
        // $this->middleware('permission:edit_client', ['only' => ['edit']]);
        // $this->middleware('permission:update_client', ['only' => ['update']]);
        // $this->middleware('permission:delete_client', ['only' => ['delete']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $clientsPaymentPeriod = ParameterValue::find($request->paymentPeriodId);

        $paymentDate = Carbon::now(); // Set to the current date

        if ($clientsPaymentPeriod && (int) $clientsPaymentPeriod->description > 0) {
            $paymentDate = Carbon::now()->addDays((int) $clientsPaymentPeriod->description);
        }

        return response()->json([
            'data' => [
                'paymentDate' => $paymentDate->format('Y-m-d')
            ]
        ]);
    }

}
