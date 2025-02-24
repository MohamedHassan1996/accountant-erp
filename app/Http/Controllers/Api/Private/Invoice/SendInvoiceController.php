<?php

// namespace App\Http\Controllers\Api\Private\Invoice;

// use App\Http\Controllers\Controller;
// use App\Mail\InvoiceEmail;
// use App\Models\Client\Client;
// use App\Models\Invoice\Invoice;

// use App\Services\Reports\ReportService;
// use App\Services\Upload\UploadService;
// use Carbon\Carbon;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Mail;
// use Illuminate\Support\Facades\Response;
// use Illuminate\Support\Facades\Storage;
// use Spatie\PdfToImage\Pdf;
// use setasign\Fpdi\Fpdi;
// use setasign\Fpdi\PdfReader;
// use Smalot\PdfParser\Parser;
// use thiagoalessio\TesseractOCR\TesseractOCR;
// use Intervention\Image\ImageManager;
// use Intervention\Image\Drivers\Imagick\Driver;

// //se Intervention\Image\Facades\Image;


// class SendInvoiceController extends Controller
// {
//     protected $uploadService;
//     public function __construct(UploadService $uploadService)
//     {
//         $this->uploadService = $uploadService;
//     }
//     public function index(Request $request)
//     {
//         $uploadedFile = $this->uploadService->uploadFile($request->file, 'uploadedInvoices');
//         $pdfPath = storage_path('app/public/'. $uploadedFile);

//         try {
//             $this->processInvoices($pdfPath);
//             return response()->json(['message' => 'Invoices sent successfully']);
//         } catch (\Exception $e) {
//             return response()->json(['error' => $e->getMessage()], 500);
//         }
//     }

//     /**
//      * Process invoices: extract Codice Fiscale and send corresponding PDFs.
//      */
//     private function processInvoices($pdfPath)
//     {
//         $outputDir = storage_path('app/public/invoices');

//         // Convert PDF to images for OCR
//         $imageFiles = $this->pdfToImages($pdfPath, $outputDir);

//         // Store detected invoices
//         $invoices = [];

//         foreach ($imageFiles as $imagePath) {
//             $codiceFiscale = $this->extractCodiceFiscale($imagePath);

//             if ($codiceFiscale) {
//                 $invoices[$codiceFiscale][] = $imagePath;
//             }
//         }

//         // Process and send invoices
//         foreach ($invoices as $codiceFiscale => $pages) {
//             $client = Client::where('cf', $codiceFiscale)->first();

//             if ($client) {
//                 $clientPdf = $this->extractPagesFromPdf($pdfPath, $pages);
//                 $this->sendInvoiceToClient($client->email, $clientPdf);
//             }
//         }
//     }

//     /**
//      * Convert PDF to images using Ghostscript for OCR processing.
//      */
//     private function pdfToImages($pdfPath, $outputDir)
//     {
//         if (!is_dir($outputDir)) {
//             mkdir($outputDir, 0777, true);
//         }

//         $outputFile = $outputDir . '/page-%d.png';
//         $gsPath = '"C:\Program Files\gs\gs10.04.0\bin\gswin64c.exe"';

//         $command = "$gsPath -dNOPAUSE -sDEVICE=png16m -r300 -o \"$outputFile\" \"$pdfPath\"";

//         exec($command . " 2>&1", $output, $returnVar);

//         if ($returnVar === 0) {
//             return glob("$outputDir/*.png"); // Return array of image paths
//         } else {
//             throw new \Exception("Failed to convert PDF to images. Error: " . implode("\n", $output));
//         }
//     }

//     /**
//      * Extract Codice Fiscale from an invoice image using OCR.
//      */
//     private function extractCodiceFiscaleFromImage($imagePath)
//     {
//         // Taglia l'immagine (esempio: x=100, y=200, larghezza=300, altezza=100)
//         $croppedImagePath = storage_path($imagePath);

//         Image::make($imagePath)
//             ->crop(40, 39, 80, 5) // Modifica queste coordinate secondo necessità
//             ->save($croppedImagePath);

//         $text = (new TesseractOCR($croppedImagePath))
//             //->lang('eng') // Se serve l'italiano, usa ->lang('ita')
//             ->run();

//         // Debug per vedere il testo estratto
//         // dd($text);

//         if (preg_match('/(?<=CODICE FISCALE )[A-Z0-9]+/', $text, $matches)) {
//             return $matches[0];
//         }

//         return null;
//     }

//     /**
//      * Extract specific pages from the original PDF and create a new PDF.
//      */
//     private function extractPagesFromPdf($pdfPath, $imagePaths)
//     {
//         $pdf = new Fpdi();
//         $pageNumbers = [];

//         // Get corresponding page numbers from image file names
//         foreach ($imagePaths as $imagePath) {
//             if (preg_match('/page-(\d+)\.png$/', $imagePath, $matches)) {
//                 $pageNumbers[] = (int) $matches[1];
//             }
//         }

//         sort($pageNumbers); // Ensure correct order

//         $pdf->setSourceFile($pdfPath);

//         foreach ($pageNumbers as $pageNo) {
//             $tplIdx = $pdf->importPage($pageNo);
//             $pdf->addPage();
//             $pdf->useTemplate($tplIdx);
//         }

//         $clientPdfPath = storage_path('app/public/invoices/invoice-' . time() . '.pdf');
//         $pdf->Output($clientPdfPath, 'F');

//         return $clientPdfPath;
//     }

//     /**
//      * Send the extracted invoice PDF to the client.
//      */
//     private function sendInvoiceToClient($email, $pdfPath)
//     {
//         Mail::raw('Here is your invoice.', function ($message) use ($email, $pdfPath) {
//             $message->to($email)
//                 ->subject('Your Invoice')
//                 ->attach($pdfPath, [
//                     'as' => 'invoice.pdf',
//                     'mime' => 'application/pdf',
//                 ]);
//         });
//     }
// }

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
class SendInvoiceController extends Controller
{
    protected $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    public function index(Request $request)
    {
        $uploadedFile = $this->uploadService->uploadFile($request->file, 'uploadedInvoices');
        $pdfPath = storage_path('app/public/' . $uploadedFile);

        try {
            $this->processInvoices($pdfPath);
            return response()->json(['message' => 'Invoices sent successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Process invoices: extract Codice Fiscale and send corresponding PDFs.
     */
    private function processInvoices($pdfPath)
    {
        $outputDir = storage_path('app/public/invoices');

        // Convert PDF to images for OCR
        $imageFiles = $this->pdfToImages($pdfPath, $outputDir);

        // Store detected invoices
        $invoices = [];

        foreach ($imageFiles as $imagePath) {
            $codiceFiscale = $this->extractCodiceFiscaleFromImage($imagePath);

            if ($codiceFiscale) {
                $invoices[$codiceFiscale][] = $imagePath;
            }
        }

        // Process and send invoices
        foreach ($invoices as $codiceFiscale => $pages) {
            $client = Client::where('cf', $codiceFiscale)->first();

            if ($client) {
                $clientPdf = $this->extractPagesFromPdf($pdfPath, $pages);
                $this->sendInvoiceToClient($client->email, $clientPdf);
            }
        }
    }

    /**
     * Convert PDF to images using Ghostscript for OCR processing.
     */
    private function pdfToImages($pdfPath, $outputDir)
    {
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $outputFile = $outputDir . '/page-%d.png';
        $gsPath = '"C:\Program Files\gs\gs10.04.0\bin\gswin64c.exe"';

        $command = "$gsPath -dNOPAUSE -sDEVICE=png16m -r300 -o \"$outputFile\" \"$pdfPath\"";

        exec($command . " 2>&1", $output, $returnVar);

        if ($returnVar === 0) {
            return glob("$outputDir/*.png"); // Return array of image paths
        } else {
            throw new \Exception("Failed to convert PDF to images. Error: " . implode("\n", $output));
        }
    }

    /**
     * Extract Codice Fiscale from an invoice image using OCR.
     */
    private function extractCodiceFiscaleFromImage($imagePath)
    {
        try {
            $croppedImagePath = storage_path('app/public/invoices/cropped_' . basename($imagePath));

                // create new image instance
            $manager = new ImageManager(new Driver());

            $image = $manager->read($imagePath);

            // crop a 40 x 40 pixel cutout from the bottom-right and move it 30 pixel down
            $image->crop(40, 39, 80, 5, 'bottom-right')->save($croppedImagePath);


            $text = (new TesseractOCR($croppedImagePath))
                //->lang('eng') // Use 'ita' if needed
                ->run();
            dd($text);

            if (preg_match('/(?<=CODICE FISCALE )[A-Z0-9]+/', $text, $matches)) {
                return $matches[0];
            }
        } catch (\Exception $e) {
            throw new \Exception("Error processing image: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Extract specific pages from the original PDF and create a new PDF.
     */
    private function extractPagesFromPdf($pdfPath, $imagePaths)
    {
        $pdf = new Fpdi();
        $pageNumbers = [];

        // Get corresponding page numbers from image file names
        foreach ($imagePaths as $imagePath) {
            if (preg_match('/page-(\d+)\.png$/', $imagePath, $matches)) {
                $pageNumbers[] = (int) $matches[1];
            }
        }

        sort($pageNumbers); // Ensure correct order

        $pdf->setSourceFile($pdfPath);

        foreach ($pageNumbers as $pageNo) {
            $tplIdx = $pdf->importPage($pageNo);
            $pdf->addPage();
            $pdf->useTemplate($tplIdx);
        }

        $clientPdfPath = storage_path('app/public/invoices/invoice-' . time() . '.pdf');
        $pdf->Output($clientPdfPath, 'F');

        return $clientPdfPath;
    }

    /**
     * Send the extracted invoice PDF to the client.
     */
    private function sendInvoiceToClient($email, $pdfPath)
    {
        Mail::raw('Here is your invoice.', function ($message) use ($email, $pdfPath) {
            $message->to($email)
                ->subject('Your Invoice')
                ->attach($pdfPath, [
                    'as' => 'invoice.pdf',
                    'mime' => 'application/pdf',
                ]);
        });
    }
}
