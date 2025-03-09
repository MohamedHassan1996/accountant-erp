<?php

namespace App\Http\Controllers\Api\Private\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\CreateClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\Client\AllClientCollection;
use App\Http\Resources\Client\ClientResource;
use App\Models\Client\ClientPayInstallment;
use App\Models\Client\ClientPayInstallmentSubData;
use App\Services\Client\ClientBankAccountService;
use App\Services\Client\ClientServiceDiscountService;
use App\Utils\PaginateCollection;
use App\Services\Client\ClientService;
use App\Services\Client\ClientAddressService;
use App\Services\Client\ClientContactService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;






class ClientController extends Controller
{
    protected $clientService;
    protected $clientAddressService;
    protected $clientContactService;
    protected $clientServiceDiscountService;

    protected $clientBankAccountService;
    public function __construct(ClientService $clientService, ClientAddressService $clientAddressService, ClientContactService $clientContactService, ClientServiceDiscountService $clientServiceDiscountService, ClientBankAccountService $clientBankAccountService)
    {
        $this->middleware('auth:api');
        $this->middleware('permission:all_clients', ['only' => ['index']]);
        $this->middleware('permission:create_client', ['only' => ['create']]);
        $this->middleware('permission:edit_client', ['only' => ['edit']]);
        $this->middleware('permission:update_client', ['only' => ['update']]);
        $this->middleware('permission:delete_client', ['only' => ['delete']]);
        $this->clientService = $clientService;
        $this->clientAddressService = $clientAddressService;
        $this->clientContactService = $clientContactService;
        $this->clientServiceDiscountService = $clientServiceDiscountService;
        $this->clientBankAccountService = $clientBankAccountService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $allClients = $this->clientService->allClients();

        return response()->json(
            new AllClientCollection(PaginateCollection::paginate($allClients, $request->pageSize?$request->pageSize:10))
        , 200);

    }

    /**
     * Show the form for creating a new resource.
     */

    public function create(CreateClientRequest $createClientRequest)
    {

        try {
            DB::beginTransaction();

            $data = $createClientRequest->validated();
            $client = $this->clientService->createClient($data);

            $addresses = $data['addresses'];
            $contacts = $data['contacts'];
            $discounts = $data['discounts'];
            $bankAccounts = $data['bankAccounts'];
            $payInstallments = $data['payInstallments'];

            foreach($addresses as $address){
                $this->clientAddressService->createAddress([
                    'clientId' => $client->id,
                    ...$address
                ]);
            }

            foreach($contacts as $contact){
                $this->clientContactService->createContact([
                    'clientId' => $client->id,
                    ...$contact
                ]);
            }

            foreach($discounts as $discount){
                $this->clientServiceDiscountService->createClientServiceDiscount([
                    'clientId' => $client->id,
                    ...$discount
                ]);
            }

            foreach($bankAccounts as $bankAccount){
                $this->clientBankAccountService->createClientBankAccount([
                    'clientId' => $client->id,
                    ...$bankAccount
                ]);
            }

            foreach($payInstallments as $payInstallment){

                $payInstallmentData = $payInstallment;

                $payInstallmentItem = ClientPayInstallment::create([
                    'client_id' => $client->id,
                    'description' => $payInstallmentData['description']??"",
                    'amount' => $payInstallmentData['amount'],
                    'start_at' => $payInstallmentData['startAt'],
                    'end_at' => $payInstallmentData['endAt']
                ]);

                foreach($payInstallmentData as $payInstallmentItemSubData){
                    ClientPayInstallmentSubData::create([
                        'clientPayInstallmentId' => $payInstallmentItem->id,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => __('messages.success.created')
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }


    }

    /**
     * Show the form for editing the specified resource.
     */

    public function edit(Request $request)
    {
        $client  =  $this->clientService->editClient($request->clientId);

        return new ClientResource($client);//new ClientResource($client)


    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClientRequest $updateClientRequest)
    {

        try {
            DB::beginTransaction();
            $client = $this->clientService->updateClient($updateClientRequest->validated());

            foreach($updateClientRequest->validated()['payInstallments'] as $payInstallment){

                $payInstallmentData = $payInstallment;

                $payInstallmentItem = ClientPayInstallment::create([
                    'client_id' => $client->id,
                    'description' => $payInstallmentData['description']??"",
                    'amount' => $payInstallmentData['amount'],
                    'start_at' => $payInstallmentData['startAt'],
                    'end_at' => $payInstallmentData['endAt']
                ]);

                foreach($payInstallmentData as $payInstallmentItemSubData){
                    ClientPayInstallmentSubData::create([
                        'clientPayInstallmentId' => $payInstallmentItem->id,
                    ]);
                }
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

    /**
     * Remove the specified resource from storage.
     */
    public function delete(Request $request)
    {

        try {
            DB::beginTransaction();
            $this->clientService->deleteClient($request->clientId);
            DB::commit();
            return response()->json([
                'message' => __('messages.success.deleted')
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }


    }

}
