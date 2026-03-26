<?php

namespace App\Http\Controllers\Api\Private\Invoice;

use App\Http\Controllers\Controller;
use App\Models\Invoice\Invoice;
use Illuminate\Http\Request;class PaidInvoicesTotalController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function __invoke(Request $request)
    {
        try {
            $request->validate([
                'startDate' => 'nullable|date',
                'endDate'   => 'nullable|date',
                'year'      => 'nullable|integer|min:2000',
            ]);

            $totalAmountCollected = $this->calcTotal(
                paidStatus: 1,
                startDate: $request->startDate,
                endDate: $request->endDate,
                year: $request->year,
                dateColumn: 'invoices.pay_date'
            );

            $totalInvoicesAmount = $this->calcTotal(
                paidStatus: null,
                startDate: $request->startDate,
                endDate: $request->endDate,
                year: $request->year,
                dateColumn: 'invoices.end_at'
            );

            $totalUncollected = $this->calcTotal(
                paidStatus: 0,
                startDate: $request->startDate,
                endDate: $request->endDate,
                year: $request->year,
                dateColumn: 'invoices.end_at'
            );

            return response()->json([
                'totalAmountCollected' => $totalAmountCollected,
                'totalInvoicesAmount'  => $totalInvoicesAmount,
                'totalUncollected'     => $totalUncollected,
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function calcTotal(?int $paidStatus, ?string $startDate, ?string $endDate, ?string $year, string $dateColumn): float
    {
        $invoices = Invoice::with(['invoiceDetails', 'client'])
            ->when(!is_null($paidStatus), fn($q) => $q->where('pay_status', $paidStatus))
            ->whereNull('invoices.deleted_at')
            ->when($startDate, fn($q) => $q->whereDate($dateColumn, '>=', $startDate))
            ->when($endDate,   fn($q) => $q->whereDate($dateColumn, '<=', $endDate))
            ->when($year,      fn($q) => $q->whereYear($dateColumn, $year))
            ->get();

        $total = 0;

        foreach ($invoices as $invoice) {
            // 1. sum price_after_discount for all details
            $subtotal = $invoice->invoiceDetails->sum('price_after_discount');

            // 2. IVA 22% always
            $subtotal = $subtotal + ($subtotal * 0.22);

            // 3. client additional tax
            if ($invoice->client && $invoice->client->total_tax > 0) {
                $subtotal = $subtotal + ($subtotal * ($invoice->client->total_tax / 100));
            }

            // 4. invoice discount
            if ($invoice->discount_amount > 0) {
                if ($invoice->discount_type == 0) {
                    // fixed
                    $subtotal -= $invoice->discount_amount;
                } elseif ($invoice->discount_type == 1) {
                    // percentage
                    $subtotal -= $subtotal * ($invoice->discount_amount / 100);
                }
            }

            $total += $subtotal;
        }

        return round($total, 2);
    }
}
