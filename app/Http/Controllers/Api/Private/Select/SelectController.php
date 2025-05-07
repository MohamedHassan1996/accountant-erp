<?php

namespace App\Http\Controllers\Api\Private\Select;

use App\Http\Controllers\Controller;
use App\Models\Invoice\Invoice;
use App\Services\Select\SelectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SelectController extends Controller
{
    private $selectService;

    public function __construct(SelectService $selectService)
    {
        $this->selectService = $selectService;
    }

    public function getSelects(Request $request)
    {
        $selectData = $this->selectService->getSelects($request->allSelects);

        return response()->json($selectData);
    }

    public function getAllInvoices(Request $request){
        $invoicesData = [];

        foreach ($request->clientIds as $clientId) {
            $invoicesData[] = [
                'label' => 'invoices-' . $clientId,
                'options' => Invoice::select([
                    'id as value',
                    DB::raw("CONCAT(number, ' - ', DATE_FORMAT(created_at, '%d/%m/%Y')) as label")
                ])->where('client_id', $clientId)->get()->toArray()
            ];
        }

        return response()->json($invoicesData);
    }


}
