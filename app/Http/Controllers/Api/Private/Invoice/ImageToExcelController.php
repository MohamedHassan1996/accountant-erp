<?php
namespace App\Http\Controllers\Api\Private\Invoice;

use App\Http\Controllers\Controller;
use App\Mail\InvoiceEmail;
use App\Models\Client\Client;
use App\Models\Invoice\Invoice;
use App\Services\Reports\ReportService;
use App\Services\Upload\UploadService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToImage\Pdf;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader;
use Smalot\PdfParser\Parser;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;


class ImageToExcelController extends Controller
{
    protected $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    public function index(Request $request)
    {
        $image = $request->file('path');
        $filename = $image->getClientOriginalName();
        $path = $image->storeAs('uploads', $filename, 'public');

        $absolutePath = storage_path('app/public/' . $path);

        $pythonScript = base_path('app/Http/Controllers/Api/Private/Invoice/image_pro2.py');
        $pythonPath = 'C:\Python312\python.exe';

        $command = "\"$pythonPath\" \"$pythonScript\" \"$absolutePath\"";

        $process = Process::run($command);

        if (!$process->successful()) {
            return response()->json([
                'error' => 'Python script failed',
                'output' => $process->output(),
                'errorOutput' => $process->errorOutput()
            ], 500);
        }

        $outputFile = storage_path('app/public/output.xlsx');
        return response()->download($outputFile);
    }


}
