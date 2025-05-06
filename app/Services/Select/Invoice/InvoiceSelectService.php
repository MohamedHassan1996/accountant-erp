<?php

namespace App\Services\Select\Invoice;

use App\Models\Invoice\Invoice;
use Illuminate\Support\Facades\DB;

class InvoiceSelectService
{
    public function getAllInvoices(?int $clientId = null)
    {
        return Invoice::select([
                'id as value',
                DB::raw("CONCAT(number, ' - ', DATE_FORMAT(created_at, '%d/%m/%Y')) as label")
            ])
            ->when($clientId !== null, function ($query) use ($clientId) {
                $query->where('client_id', $clientId);
            })
            ->get();
    }

}

