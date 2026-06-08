<?php

namespace App\Imports;

use App\Enums\Client\AddableToBulk;
use App\Models\Client\Client;
use App\Models\Client\ClientAddress;
use App\Models\Client\ClientBankAccount;
use App\Models\Parameter\ParameterValue;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Row;

class ClientImport implements OnEachRow, WithChunkReading, WithStartRow
{
    private const WORK_ADDRESS_TYPE_ID = 2;
    private const PAYMENT_TYPE_PARAMETER_ID = 4;
    private const BANK_PARAMETER_ID = 10;

    private array $paymentTypeCache = [];
    private array $bankCache = [];
    private array $clientCacheByNameAndVat = [];
    private array $clientCacheByNameAndCf = [];
    private array $clientCacheByVat = [];
    private array $clientCacheByCf = [];

    public function startRow(): int
    {
        return 2;
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function onRow(Row $importRow): void
    {
        $row = $importRow->toArray();
        $ragioneSociale = $this->normalizeString($row[1] ?? null);

        if ($ragioneSociale === null) {
            return;
        }

        $iva = $this->normalizeVat($row[2] ?? null);
        $cf = $this->normalizeTaxCode($row[3] ?? null);
        $paymentTypeId = $this->resolvePaymentTypeId(
            $row[12] ?? null,
            $row[13] ?? null
        );
        $clientImportData = $this->buildClientImportData($row, $ragioneSociale, $iva, $cf, $paymentTypeId);

        $client = $this->findExistingClient($ragioneSociale, $iva, $cf);

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
        } else {
            $this->fillMissingClientData($client, $clientImportData);
        }

        $this->rememberClient($client);

        $addressChanged = $this->syncClientAddress($client, $row);
        $bankAccountChanged = $this->syncClientBankAccount($client, $row);

        if (!$client->wasRecentlyCreated && ($addressChanged || $bankAccountChanged)) {
            $client->touch();
        }
    }

    private function findExistingClient(?string $ragioneSociale, ?string $iva, ?string $cf): ?Client
    {
        $cachedCandidates = [];
        $nameAndVatKey = $this->buildClientCompositeKey($ragioneSociale, $iva);
        $nameAndCfKey = $this->buildClientCompositeKey($ragioneSociale, $cf);

        if ($nameAndVatKey !== null && array_key_exists($nameAndVatKey, $this->clientCacheByNameAndVat)) {
            $cachedCandidates[] = $this->clientCacheByNameAndVat[$nameAndVatKey];
        }

        if ($nameAndCfKey !== null && array_key_exists($nameAndCfKey, $this->clientCacheByNameAndCf)) {
            $cachedCandidates[] = $this->clientCacheByNameAndCf[$nameAndCfKey];
        }

        if ($iva !== null && array_key_exists($iva, $this->clientCacheByVat)) {
            $cachedCandidates[] = $this->clientCacheByVat[$iva];
        }

        if ($cf !== null && array_key_exists($cf, $this->clientCacheByCf)) {
            $cachedCandidates[] = $this->clientCacheByCf[$cf];
        }

        if ($cachedCandidates !== []) {
            return $this->selectBestMatchingClient(collect($cachedCandidates), $ragioneSociale, $iva, $cf);
        }

        if ($ragioneSociale === null) {
            return null;
        }

        $clients = Client::query()
            ->where(function ($query) use ($ragioneSociale, $iva, $cf) {
                $query->where('ragione_sociale', $ragioneSociale);

                if ($iva !== null) {
                    $query->orWhere('iva', $iva);
                }

                if ($cf !== null) {
                    $query->orWhere('cf', $cf);
                }
            })
            ->get();

        $client = $this->selectBestMatchingClient($clients, $ragioneSociale, $iva, $cf);

        if ($client) {
            $this->rememberClient($client);
        }

        return $client;
    }

    private function fillMissingClientData(Client $client, array $data): bool
    {
        $dirty = false;

        foreach ($data as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ($this->shouldReplaceClientField($client, $field, $value)) {
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
        array $row,
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

    private function syncClientAddress(Client $client, array $row): bool
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
            'parameter_value_id' => self::WORK_ADDRESS_TYPE_ID,
            'address' => $address,
            'cap' => $cap,
            'city' => $city,
            'province' => $province,
            'region' => $region,
        ]);

        return true;
    }

    private function syncClientBankAccount(Client $client, array $row): bool
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
                ->where('parameter_id', self::PAYMENT_TYPE_PARAMETER_ID)
                ->where('parameter_value', $normalizedDescription)
                ->first();
        }

        if (!$paymentType && $normalizedCode !== null) {
            $paymentType = ParameterValue::query()
                ->where('parameter_id', self::PAYMENT_TYPE_PARAMETER_ID)
                ->where('description', $normalizedCode)
                ->first();
        }

        if (!$paymentType && $normalizedDescription !== null) {
            $nextOrder = ((int) ParameterValue::query()
                ->where('parameter_id', self::PAYMENT_TYPE_PARAMETER_ID)
                ->max('parameter_order')) + 1;

            $paymentType = ParameterValue::create([
                'parameter_id' => self::PAYMENT_TYPE_PARAMETER_ID,
                'parameter_value' => $normalizedDescription,
                'description' => $normalizedCode,
                'parameter_order' => $nextOrder,
                'is_default' => 0,
            ]);
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
            ->where('parameter_id', self::BANK_PARAMETER_ID)
            ->where(function ($query) use ($bankName) {
                $query->where('parameter_value', $bankName)
                    ->orWhere('description', $bankName);
            })
            ->first();

        if (!$bank) {
            $nextOrder = ((int) ParameterValue::query()
                ->where('parameter_id', self::BANK_PARAMETER_ID)
                ->max('parameter_order')) + 1;

            $bank = ParameterValue::create([
                'parameter_id' => self::BANK_PARAMETER_ID,
                'parameter_value' => $bankName,
                'description' => $bankName,
                'parameter_order' => $nextOrder,
                'is_default' => 0,
            ]);
        }

        return $this->bankCache[$bankName] = $bank->id;
    }

    private function rememberClient(Client $client): void
    {
        $nameAndVatKey = $this->buildClientCompositeKey($client->ragione_sociale, $client->iva);
        $nameAndCfKey = $this->buildClientCompositeKey($client->ragione_sociale, $client->cf);

        if ($nameAndVatKey !== null) {
            $this->clientCacheByNameAndVat[$nameAndVatKey] = $this->pickMoreCompleteClient(
                $this->clientCacheByNameAndVat[$nameAndVatKey] ?? null,
                $client
            );
        }

        if ($nameAndCfKey !== null) {
            $this->clientCacheByNameAndCf[$nameAndCfKey] = $this->pickMoreCompleteClient(
                $this->clientCacheByNameAndCf[$nameAndCfKey] ?? null,
                $client
            );
        }

        if (!empty($client->iva)) {
            $this->clientCacheByVat[$client->iva] = $this->pickMoreCompleteClient(
                $this->clientCacheByVat[$client->iva] ?? null,
                $client
            );
        }

        if (!empty($client->cf)) {
            $this->clientCacheByCf[$client->cf] = $this->pickMoreCompleteClient(
                $this->clientCacheByCf[$client->cf] ?? null,
                $client
            );
        }
    }

    private function buildClientCompositeKey(?string $ragioneSociale, ?string $value): ?string
    {
        if ($ragioneSociale === null || $value === null) {
            return null;
        }

        return $ragioneSociale . '|' . $value;
    }

    private function selectBestMatchingClient($clients, ?string $ragioneSociale, ?string $iva, ?string $cf): ?Client
    {
        $clients = collect($clients)
            ->filter(fn (Client $client) => $this->isCandidateMatch($client, $ragioneSociale, $iva, $cf))
            ->unique('id')
            ->values();

        if ($clients->isEmpty()) {
            return null;
        }

        return $clients
            ->sortByDesc(fn (Client $client) => $this->clientCompletenessScore($client))
            ->first();
    }

    private function isCandidateMatch(Client $client, ?string $ragioneSociale, ?string $iva, ?string $cf): bool
    {
        $sameName = $ragioneSociale !== null && $client->ragione_sociale === $ragioneSociale;
        $sameVat = $iva !== null && $client->iva === $iva;
        $sameCf = $cf !== null && $client->cf === $cf;

        if ($sameName && ($sameVat || $sameCf)) {
            return true;
        }

        if ($sameVat || $sameCf) {
            return true;
        }

        if ($sameName && (
            ($iva !== null && empty($client->iva)) ||
            ($cf !== null && empty($client->cf))
        )) {
            return true;
        }

        return false;
    }

    private function clientCompletenessScore(Client $client): int
    {
        $score = 0;

        foreach ([
            'iva',
            'cf',
            'email',
            'email_f24',
            'phone',
            'payment_type_two_id',
            'abi',
            'cab',
            'sdi',
        ] as $field) {
            if (!empty($client->{$field})) {
                $score += 10;
            }
        }

        if (!empty($client->ragione_sociale)) {
            $score += mb_strlen($client->ragione_sociale);
        }

        return $score;
    }

    private function pickMoreCompleteClient(?Client $current, Client $candidate): Client
    {
        if ($current === null) {
            return $candidate;
        }

        return $this->clientCompletenessScore($candidate) > $this->clientCompletenessScore($current)
            ? $candidate
            : $current;
    }

    private function shouldReplaceClientField(Client $client, string $field, mixed $value): bool
    {
        $currentValue = $client->{$field};

        if ($currentValue === $value) {
            return false;
        }

        if ($currentValue === null || $currentValue === '') {
            return true;
        }

        if ($field === 'ragione_sociale') {
            return mb_strlen((string) $value) > mb_strlen((string) $currentValue);
        }

        return false;
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
