<?php

namespace App\Http\Controllers;

use App\Imports\ServiceCategoryImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ImportServiceCategoryController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        Excel::import(new ServiceCategoryImport, $request->file('file'));

        return response()->json([
            'message' => 'Service categories imported successfully',
        ], 200);
    }
}
