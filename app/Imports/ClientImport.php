<?php

namespace App\Imports;

use App\Enums\Client\AddableToBulk;
use App\Models\Client\Client;
use App\Models\Client\ClientAddress;
use App\Models\Client\ClientBankAccount;
use App\Models\Parameter\ParameterValue;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class ClientImport implements ToCollection
{
    private array $paymentTypeCache = [];
    private array $bankCache = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            if ($index < 10) {
                continue;
            }

            $ragioneSociale = $this->normalizeString($row[1] ?? null);

            if ($ragioneSociale === null) {
                continue;
            }

            $iva = $this->normalizeVat($row[2] ?? null);
            $cf = $this->normalizeTaxCode($row[3] ?? null) ?? $iva;
            $paymentTypeId = $this->resolvePaymentTypeId(
                $row[12] ?? null,
                $row[13] ?? null
            );
            $clientImportData = $this->buildClientImportData($row, $ragioneSociale, $iva, $cf, $paymentTypeId);

            $client = $this->findExistingClient($ragioneSociale, $iva, $cf);

            $clientChanged = false;

            if (!$client) {
                $client = Client::create(array_merge($clientImportData, [
                    'addable_to_bulk_invoice' => AddableToBulk::ADDABLE->value,
                    'allowed_days_to_pay' => 0,
                    'is_company' => $iva !== null,
                    'price' => 0,
                    'monthly_price' => 0,
                    'hours_per_month' => 0,
                    'total_tax' => 0,
                    'limit_decreto' => 0,
                    'proforma' => false,
                ]));
                $clientChanged = true;
            } else {
                $clientChanged = $this->fillMissingClientData($client, $clientImportData);
            }

            $addressChanged = $this->syncClientAddress($client, $row);
            $bankAccountChanged = $this->syncClientBankAccount($client, $row);

            if (!$client->wasRecentlyCreated && ($addressChanged || $bankAccountChanged)) {
                $client->touch();
            }
        }
    }

    private function findExistingClient(?string $ragioneSociale, ?string $iva, ?string $cf): ?Client
    {
        if ($ragioneSociale !== null) {
            $clientByName = Client::query()
                ->where('ragione_sociale', $ragioneSociale)
                ->first();

            if ($clientByName) {
                return $clientByName;
            }
        }

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

    private function fillMissingClientData(Client $client, array $data): bool
    {
        $dirty = false;

        foreach ($data as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ($client->{$field} !== $value) {
                $client->{$field} = $value;
                $dirty = true;
            }
        }

        if ($dirty) {
            $client->save();
        }

        return $dirty;
    }

    private function buildClientImportData(
        Collection|array $row,
        string $ragioneSociale,
        ?string $iva,
        ?string $cf,
        ?int $paymentTypeId
    ): array {
        return [
            'ragione_sociale' => $ragioneSociale,
            'iva' => $iva,
            'cf' => $cf,
            'phone' => $this->normalizeString($row[11] ?? null),
            'email' => $this->normalizeEmail($row[19] ?? null),
            'email_f24' => $this->normalizeEmail($row[20] ?? null),
            'payment_type_two_id' => $paymentTypeId,
            'abi' => $this->normalizeBankCode($row[15] ?? null),
            'cab' => $this->normalizeBankCode($row[16] ?? null),
            'sdi' => $this->normalizeSdi($row[10] ?? null),
            'is_company' => $iva !== null ? 1 : null,
        ];
    }

    private function syncClientAddress(Client $client, Collection|array $row): bool
    {
        $address = $this->normalizeString($row[5] ?? null);
        $cap = $this->normalizeString($row[6] ?? null);
        $city = $this->normalizeString($row[7] ?? null);
        $province = $this->normalizeString($row[8] ?? null);
        $region = $this->normalizeString($row[9] ?? null);

        if ($address === null && $cap === null && $city === null && $province === null) {
            return false;
        }

        $exists = ClientAddress::query()
            ->where('client_id', $client->id)
            ->where('address', $address)
            ->where('cap', $cap)
            ->where('city', $city)
            ->where('province', $province)
            ->exists();

        if ($exists) {
            return false;
        }

        ClientAddress::create([
            'client_id' => $client->id,
            'address' => $address,
            'cap' => $cap,
            'city' => $city,
            'province' => $province,
            'region' => $region,
        ]);

        return true;
    }

    private function syncClientBankAccount(Client $client, Collection|array $row): bool
    {
        $bankName = $this->normalizeString($row[14] ?? null);
        $abi = $this->normalizeBankCode($row[15] ?? null);
        $cab = $this->normalizeBankCode($row[16] ?? null);

        if ($bankName === null && $abi === null && $cab === null) {
            return false;
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
            $clientChanged = false;
            $bankAccountChanged = false;

            if (!$client->abi && $abi !== null) {
                $client->abi = $abi;
                $clientChanged = true;
            }

            if (!$client->cab && $cab !== null) {
                $client->cab = $cab;
                $clientChanged = true;
            }

            if ($clientChanged) {
                $client->save();
            }

            if (!$client->bankAccounts()->where('is_main', true)->exists()) {
                $existingAccount->is_main = true;
                $existingAccount->save();
                $bankAccountChanged = true;
            }

            return $clientChanged || $bankAccountChanged;
        }

        ClientBankAccount::create([
            'client_id' => $client->id,
            'bank_id' => $bankId,
            'iban' => null,
            'abi' => $abi,
            'cab' => $cab,
            'is_main' => !$client->bankAccounts()->exists(),
        ]);

        return true;
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
