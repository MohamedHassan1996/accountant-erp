<?php

namespace App\Http\Controllers;

use App\Imports\ClientImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ImportClientController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        Excel::import(new ClientImport, $request->file('file'));

        return response()->json([
            'message' => 'Clients imported successfully',
        ], 200);
    }
}
