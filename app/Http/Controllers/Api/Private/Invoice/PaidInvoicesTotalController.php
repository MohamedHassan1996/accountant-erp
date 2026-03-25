<?php

namespace App\Http\Controllers\Api\Private\Invoice;

use App\Http\Controllers\Controller;
use App\Models\Invoice\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaidInvoicesTotalController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function __invoke(Request $request)
    {
        try {
            $request->validate([
                'month' => 'nullable|integer|min:1|max:12',
                'year'  => 'nullable|integer|min:2000',
            ]);

            $total = Invoice::where('pay_status', 1)
                ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                ->whereNull('invoices.deleted_at')
                ->whereNull('invoice_details.deleted_at')
                ->when($request->filled('year'),  fn($q) => $q->whereYear('invoices.pay_date', $request->year))
                ->when($request->filled('month'), fn($q) => $q->whereMonth('invoices.pay_date', $request->month))
                ->sum(DB::raw('COALESCE(invoice_details.price_after_discount, invoice_details.price, 0)'));

            $overdueCount = Invoice::where('pay_status', 0)
                ->whereNotNull('end_at')
                ->where('end_at', '<', Carbon::today())
                ->whereNull('deleted_at')
                ->count();

            $aboutToExpireCount = Invoice::where('pay_status', 0)
                ->whereNotNull('end_at')
                ->whereBetween('end_at', [Carbon::today(), Carbon::today()->addDays(10)])
                ->whereNull('deleted_at')
                ->count();

            return response()->json([
                'totalAmountCollected' => round($total, 2),
                'overdueUnpaidCount'   => $overdueCount,
                'aboutToExpireCount'   => $aboutToExpireCount,
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
