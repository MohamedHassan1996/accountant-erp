<?php

namespace App\Services\Select;

use App\Models\Client\ClientBankAccount;
use Illuminate\Support\Facades\DB;

class ClientBankAccountSelectService
{
    public function getAllClientBankAccounts(int $clientId)
    {
        return ClientBankAccount::query()
            ->leftJoin('parameter_values', 'client_bank_accounts.bank_id', '=', 'parameter_values.id')
            ->where('client_bank_accounts.client_id', $clientId)
            ->whereNull('client_bank_accounts.deleted_at')
            ->orderByDesc('client_bank_accounts.is_main')
            ->orderByDesc('client_bank_accounts.id')
            ->select([
                DB::raw('CONCAT(client_bank_accounts.id, "##", client_bank_accounts.is_main) as value'),
                DB::raw("
                    TRIM(
                        CONCAT(
                            COALESCE(parameter_values.parameter_value, ''),
                            CASE
                                WHEN parameter_values.parameter_value IS NOT NULL AND client_bank_accounts.iban IS NOT NULL AND client_bank_accounts.iban != ''
                                    THEN ' - '
                                ELSE ''
                            END,
                            COALESCE(client_bank_accounts.iban, '')
                        )
                    ) as label
                "),
            ])
            ->get();
    }
}
