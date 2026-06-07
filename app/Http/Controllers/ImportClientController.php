<?php

namespace App\Http\Controllers;

use App\Imports\ClientImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ImportClientController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');
        set_time_limit(0);

        DB::beginTransaction();

        try {
            Excel::import(new ClientImport, $request->file('file'));
            DB::commit();
        } catch (\Throwable $throwable) {
            DB::rollBack();

            return response()->json([
                'message' => 'Clients import failed',
                'error' => $throwable->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Clients imported successfully',
        ], 200);
    }
}
