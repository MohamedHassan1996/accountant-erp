<?php

namespace App\Services\Select\Invoice;

use App\Models\Invoice\Invoice;

class InvoiceSelectService
{
    public function getAllInvoices()
    {
        return Invoice::select(['id as value', 'number as label'])->get();
    }
}

