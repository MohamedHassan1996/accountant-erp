<?php

namespace App\Http\Controllers\Api\Private\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\CreateClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\Client\AllClientCollection;
use App\Http\Resources\Client\ClientResource;
use App\Models\Client\Client;
use App\Utils\PaginateCollection;
use App\Services\Client\ClientService;
use App\Services\Client\ClientAddressService;
use App\Services\Client\ClientContactService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class ClientPaymentTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        // $this->middleware('permission:all_clients', ['only' => ['index']]);
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
        $clientsPaymentType = Client::whereIn('id', $request->clientIds)->pluck('payment_type_two_id')->toArray();

        return response()->json([
            'data' => [
                'clientsPaymentType' => $clientsPaymentType]
            ]);
    }

}
