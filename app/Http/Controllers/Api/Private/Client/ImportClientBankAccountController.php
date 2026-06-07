<?php

namespace App\Http\Controllers\Api\Private\Client;

use App\Http\Controllers\Controller;
use App\Imports\ClientBankAccountImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ImportClientBankAccountController extends Controller
{

    protected $clientBankAccountService;


    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');
        set_time_limit(0);

        DB::beginTransaction();

        try {
            Excel::import(new ClientBankAccountImport, $request->file('file'));
            DB::commit();
        } catch (\Throwable $throwable) {
            DB::rollBack();

            return response()->json([
                'message' => 'Bank accounts import failed',
                'error' => $throwable->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Bank accounts imported successfully',
        ]);
    }

}
