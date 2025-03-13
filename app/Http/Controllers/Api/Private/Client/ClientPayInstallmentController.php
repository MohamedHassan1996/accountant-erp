<?php

namespace App\Http\Controllers\Api\Private\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\PayInstallment\PayInstallmentResource;
use App\Models\Client\ClientPayInstallment;
use App\Models\Client\ClientPayInstallmentSubData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class ClientPayInstallmentController extends Controller
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
        $allClientPayInstallments = ClientPayInstallment::with('payInstallmentSubData')->where('client_id', $request->clientId)->get();

        return PayInstallmentResource::collection($allClientPayInstallments);

    }

    /**
     * Show the form for editing the specified resource.
     */

    public function edit(Request $request)
    {
        $payInstallment  =  ClientPayInstallment::find($request->payInstallmentId);

        return new PayInstallmentResource($payInstallment);


    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {

        try {
            DB::beginTransaction();

            $payInstallmentSubData = $request->all()['payInstallmentSubData'];
            $payInstallment = ClientPayInstallment::with('payInstallmentSubData')->find($request->payInstallmentId);
            $payInstallment->update([
                'client_id' => $request->clientId,
                'start_at' => $request->startAt,
                'end_at' => $request->endAt,
                'amount' => $request->amount,
                'description' => $request->description
            ]);

            $payInstallment->payInstallmentSubData()->forceDelete();

            foreach($payInstallmentSubData as $payInstallmentSubDataItem){
                ClientPayInstallmentSubData::create([
                    'client_pay_installment_id' => $payInstallment->id,
                    'price' => $payInstallmentSubDataItem['price'],
                    'description' => $payInstallmentSubDataItem['description']
                ]);
            }


            DB::commit();
            return response()->json([
                 'message' => __('messages.success.updated')
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }


    }

}
