<?php

namespace App\Imports;

use App\Models\ServiceCategory\ServiceCategory;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class ServiceCategoryImport implements ToCollection
{
    public function collection(Collection $rows): void
    {
        $existingNames = ServiceCategory::query()
            ->pluck('name')
            ->map(fn ($name) => $this->normalizeName($name))
            ->filter()
            ->flip();

        foreach ($rows as $row) {
            $name = $this->normalizeName($row[0] ?? null);

            if ($name === null || isset($existingNames[$name])) {
                continue;
            }

            ServiceCategory::create([
                'name' => $name,
                'description' => null,
                'price' => 0,
                'add_to_invoice' => 0,
                'service_type_id' => null,
            ]);

            $existingNames[$name] = true;
        }
    }

    private function normalizeName(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
