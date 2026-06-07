<?php

namespace App\Imports;

use App\Enums\Client\AddableToBulk;
use App\Models\Client\Client;
use App\Models\Client\ClientAddress;
use App\Models\Client\ClientBankAccount;
use App\Models\Parameter\ParameterValue;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ClientImport implements ToCollection, WithHeadingRow
{
    private array $paymentTypeCache = [];
    private array $bankCache = [];

    public function headingRow(): int
    {
        return 10;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $accountCode = $this->normalizeString($row['conto'] ?? null);

            if (!$this->shouldImportRow($accountCode)) {
                continue;
            }

            $ragioneSociale = $this->normalizeString($row['ragione_sociale'] ?? null);

            if ($ragioneSociale === null) {
                continue;
            }

            $iva = $this->normalizeVat($row['partita_iva'] ?? null);
            $cf = $this->normalizeTaxCode($row['codice_fiscale'] ?? null) ?? $iva;
            $paymentTypeId = $this->resolvePaymentTypeId(
                $row['cod_pag'] ?? null,
                $row['pagamento'] ?? null
            );

            $client = $this->findExistingClient($ragioneSociale, $iva, $cf);

            if (!$client) {
                $client = Client::create([
                    'ragione_sociale' => $ragioneSociale,
                    'iva' => $iva,
                    'cf' => $cf,
                    'phone' => $this->normalizeString($row['telefono'] ?? null),
                    'email' => $this->normalizeEmail($row['email'] ?? null),
                    'email_f24' => $this->normalizeEmail($row['pec'] ?? null),
                    'payment_type_two_id' => $paymentTypeId,
                    'abi' => $this->normalizeString($row['abi'] ?? null),
                    'cab' => $this->normalizeString($row['cab'] ?? null),
                    'sdi' => $this->normalizeSdi($row['codice_sdi'] ?? null),
                    'addable_to_bulk_invoice' => AddableToBulk::ADDABLE->value,
                    'allowed_days_to_pay' => 0,
                    'is_company' => $iva !== null,
                    'price' => 0,
                    'monthly_price' => 0,
                    'hours_per_month' => 0,
                    'total_tax' => 0,
                    'limit_decreto' => 0,
                    'proforma' => false,
                ]);
            } else {
                $this->fillMissingClientData($client, [
                    'iva' => $iva,
                    'cf' => $cf,
                    'phone' => $this->normalizeString($row['telefono'] ?? null),
                    'email' => $this->normalizeEmail($row['email'] ?? null),
                    'email_f24' => $this->normalizeEmail($row['pec'] ?? null),
                    'payment_type_two_id' => $paymentTypeId,
                    'abi' => $this->normalizeString($row['abi'] ?? null),
                    'cab' => $this->normalizeString($row['cab'] ?? null),
                    'sdi' => $this->normalizeSdi($row['codice_sdi'] ?? null),
                    'is_company' => $iva !== null ? 1 : null,
                ]);
            }

            $this->syncClientAddress($client, $row);
            $this->syncClientBankAccount($client, $row);
        }
    }

    private function shouldImportRow(?string $accountCode): bool
    {
        if ($accountCode === null) {
            return false;
        }

        return str_starts_with($accountCode, '501.');
    }

    private function findExistingClient(?string $ragioneSociale, ?string $iva, ?string $cf): ?Client
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

    private function fillMissingClientData(Client $client, array $data): void
    {
        $dirty = false;

        foreach ($data as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $currentValue = $client->{$field};

            if ($currentValue === null || $currentValue === '') {
                $client->{$field} = $value;
                $dirty = true;
            }
        }

        if ($dirty) {
            $client->save();
        }
    }

    private function syncClientAddress(Client $client, array $row): void
    {
        $address = $this->normalizeString($row['indirizzo'] ?? null);
        $cap = $this->normalizeString($row['cap'] ?? null);
        $city = $this->normalizeString($row['localita'] ?? null);
        $province = $this->normalizeString($row['prov'] ?? null);
        $region = $this->normalizeString($row['nazione'] ?? null);

        if ($address === null && $cap === null && $city === null && $province === null) {
            return;
        }

        $exists = ClientAddress::query()
            ->where('client_id', $client->id)
            ->where('address', $address)
            ->where('cap', $cap)
            ->where('city', $city)
            ->where('province', $province)
            ->exists();

        if ($exists) {
            return;
        }

        ClientAddress::create([
            'client_id' => $client->id,
            'address' => $address,
            'cap' => $cap,
            'city' => $city,
            'province' => $province,
            'region' => $region,
        ]);
    }

    private function syncClientBankAccount(Client $client, array $row): void
    {
        $bankName = $this->normalizeString($row['banca_di_appoggio'] ?? null);
        $abi = $this->normalizeBankCode($row['abi'] ?? null);
        $cab = $this->normalizeBankCode($row['cab'] ?? null);

        if ($bankName === null && $abi === null && $cab === null) {
            return;
        }

        $bankId = $this->resolveBankId($bankName);

        $existingAccount = ClientBankAccount::query()
            ->where('client_id', $client->id)
            ->where('bank_id', $bankId)
            ->where('abi', $abi)
            ->where('cab', $cab)
            ->where(function ($query) {
                $query->whereNull('iban')->orWhere('iban', '');
            })
            ->first();

        if ($existingAccount) {
            if (!$client->abi && $abi !== null) {
                $client->abi = $abi;
            }

            if (!$client->cab && $cab !== null) {
                $client->cab = $cab;
            }

            if ($client->isDirty(['abi', 'cab'])) {
                $client->save();
            }

            if (!$client->bankAccounts()->where('is_main', true)->exists()) {
                $existingAccount->is_main = true;
                $existingAccount->save();
            }

            return;
        }

        ClientBankAccount::create([
            'client_id' => $client->id,
            'bank_id' => $bankId,
            'iban' => null,
            'abi' => $abi,
            'cab' => $cab,
            'is_main' => !$client->bankAccounts()->exists(),
        ]);
    }

    private function resolvePaymentTypeId(mixed $paymentCode, mixed $paymentDescription): ?int
    {
        $normalizedCode = $this->normalizeString($paymentCode);
        $normalizedDescription = $this->normalizeString($paymentDescription);

        if ($normalizedCode === null && $normalizedDescription === null) {
            return null;
        }

        $cacheKey = ($normalizedCode ?? '-') . '|' . ($normalizedDescription ?? '-');

        if (array_key_exists($cacheKey, $this->paymentTypeCache)) {
            return $this->paymentTypeCache[$cacheKey];
        }

        $paymentType = null;

        if ($normalizedDescription !== null) {
            $paymentType = ParameterValue::query()
                ->where('parameter_value', $normalizedDescription)
                ->orWhere('description', $normalizedDescription)
                ->first();
        }

        if (!$paymentType && $normalizedCode !== null) {
            $paymentType = ParameterValue::query()
                ->where('parameter_value', $normalizedCode)
                ->orWhere('description', $normalizedCode)
                ->first();
        }

        return $this->paymentTypeCache[$cacheKey] = $paymentType?->id;
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

    private function normalizeSdi(mixed $value): ?string
    {
        $value = $this->normalizeString($value);

        if ($value === null) {
            return null;
        }

        return strtoupper($value);
    }

    private function normalizeEmail(mixed $value): ?string
    {
        $value = $this->normalizeString($value);

        if ($value === null) {
            return null;
        }

        return strtolower($value);
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
