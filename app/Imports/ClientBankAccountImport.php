<?php

namespace App\Imports;

use App\Models\Client\Client;
use App\Models\Client\ClientBankAccount;
use App\Models\Parameter\ParameterValue;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ClientBankAccountImport implements ToCollection, WithHeadingRow
{
    private array $bankCache = [];

    public function headingRow(): int
    {
        return 10;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $cf = $this->normalizeTaxCode($row['codice_fiscale'] ?? $row['cf'] ?? null);
            $iva = $this->normalizeVat($row['partita_iva'] ?? $row['iva'] ?? null);
            $ragioneSociale = $this->normalizeString($row['ragione_sociale'] ?? null);

            if ($cf === null && $iva === null && $ragioneSociale === null) {
                continue;
            }

            $client = $this->findClient($ragioneSociale, $iva, $cf);

            if (!$client) {
                continue;
            }

            $bankName = $this->normalizeString($row['banca_di_appoggio'] ?? $row['banca'] ?? null);
            $abi = $this->normalizeBankCode($row['abi'] ?? null);
            $cab = $this->normalizeBankCode($row['cab'] ?? null);
            $iban = $this->normalizeString($row['iban'] ?? null);

            if ($bankName === null && $abi === null && $cab === null && $iban === null) {
                continue;
            }

            $bankId = $this->resolveBankId($bankName);

            $exists = ClientBankAccount::query()
                ->where('client_id', $client->id)
                ->where('bank_id', $bankId)
                ->where('iban', $iban)
                ->where('abi', $abi)
                ->where('cab', $cab)
                ->exists();

            if ($exists) {
                continue;
            }

            ClientBankAccount::create([
                'client_id' => $client->id,
                'bank_id' => $bankId,
                'iban' => $iban,
                'abi' => $abi,
                'cab' => $cab,
                'is_main' => !$client->bankAccounts()->exists(),
            ]);
        }
    }

    private function findClient(?string $ragioneSociale, ?string $iva, ?string $cf): ?Client
    {
        return Client::query()
            ->where(function ($query) use ($ragioneSociale, $iva, $cf) {
                if ($iva !== null) {
                    $query->orWhere('iva', $iva);
                }

                if ($cf !== null) {
                    $query->orWhere('cf', $cf);
                }

                if ($ragioneSociale !== null) {
                    $query->orWhere('ragione_sociale', $ragioneSociale);
                }
            })
            ->first();
    }

    private function resolveBankId(?string $bankName): ?int
    {
        if ($bankName === null) {
            return null;
        }

        if (array_key_exists($bankName, $this->bankCache)) {
            return $this->bankCache[$bankName];
        }

        $bank = ParameterValue::query()
            ->where('parameter_id', 10)
            ->where(function ($query) use ($bankName) {
                $query->where('parameter_value', $bankName)
                    ->orWhere('description', $bankName);
            })
            ->first();

        if (!$bank) {
            $nextOrder = ((int) ParameterValue::query()->where('parameter_id', 10)->max('parameter_order')) + 1;

            $bank = ParameterValue::create([
                'parameter_id' => 10,
                'parameter_value' => $bankName,
                'description' => $bankName,
                'parameter_order' => $nextOrder,
                'is_default' => 0,
            ]);
        }

        return $this->bankCache[$bankName] = $bank->id;
    }

    private function normalizeVat(mixed $value): ?string
    {
        $value = $this->normalizeString($value);

        if ($value === null) {
            return null;
        }

        $value = strtoupper(str_replace(' ', '', $value));

        if (str_starts_with($value, 'IT')) {
            $value = substr($value, 2);
        }

        return $value !== '' ? $value : null;
    }

    private function normalizeTaxCode(mixed $value): ?string
    {
        $value = $this->normalizeString($value);

        if ($value === null) {
            return null;
        }

        return strtoupper(str_replace(' ', '', $value));
    }

    private function normalizeBankCode(mixed $value): ?string
    {
        $value = $this->normalizeString($value);

        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits !== '' ? str_pad($digits, 5, '0', STR_PAD_LEFT) : null;
    }

    private function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
