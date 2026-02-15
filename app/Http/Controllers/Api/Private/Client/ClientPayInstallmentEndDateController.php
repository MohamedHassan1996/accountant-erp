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

        // Get holidays from parameter_values where parameter_order = 11
        $holidays = ParameterValue::where('parameter_order', 11)
            ->pluck('parameter_value')
            ->map(function($date) {
                return Carbon::parse($date)->format('Y-m-d');
            })
            ->toArray();

        // Adjust start date if it falls on weekend or holiday
        $startAt = $this->adjustForWeekendsAndHolidays($startAt, $holidays);

        $allowedDaysToPay = $client->allowed_days_to_pay ?? 0;

        $installmentEndDataAdd = ParameterValue::where('id', $request->paymentTypeId)->first();

        $installmentEndDataAddMonth = ceil($installmentEndDataAdd->description / 30);

        // Calculate end date as last day of the month after adding months
        $endDate = $startAt->copy()
            ->addMonths($installmentEndDataAddMonth)
            ->subMonth() // Go back one month
            ->endOfMonth(); // Get last day of that month

        $isSpecialMonthEnd = in_array($endDate->format('m-d'), ['08-31', '12-31']);

        if ($isSpecialMonthEnd) {
            $endDate->addDays(10);
        } else {
            $endDate->addDays($allowedDaysToPay);
        }

        return response()->json([
            'startAt' => $startAt->format('Y-m-d'),
            'endAt' => $endDate->format('Y-m-d'),
        ]);

    }

    /**
     * Adjust date if it falls on weekend (Saturday/Sunday) or holiday
     * Move to next Monday or next working day
     */
    private function adjustForWeekendsAndHolidays(Carbon $date, array $holidays): Carbon
    {
        $adjustedDate = $date->copy();

        // Keep adjusting until we find a working day
        while (true) {
            $dayOfWeek = $adjustedDate->dayOfWeek;
            $dateString = $adjustedDate->format('Y-m-d');

            // Check if Saturday (6) or Sunday (0)
            if ($dayOfWeek == Carbon::SATURDAY || $dayOfWeek == Carbon::SUNDAY) {
                // Move to next Monday
                $adjustedDate->next(Carbon::MONDAY);
                continue;
            }

            // Check if it's a holiday
            if (in_array($dateString, $holidays)) {
                $adjustedDate->addDay();
                continue;
            }

            // It's a working day
            break;
        }

        return $adjustedDate;
    }


}
