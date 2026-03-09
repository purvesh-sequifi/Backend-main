<?php

namespace App\Services\Sales;

use App\Models\LegacyApiRawDataHistory;
use Carbon\Carbon;

class RawDataHistoryService
{
    public function save(array $data): LegacyApiRawDataHistory
    {
        return LegacyApiRawDataHistory::create($data);
    }

    private function looksLikeDate(string $value): bool
    {
        $value = trim($value);

        return preg_match('/^\d{1,4}[-\/\.]\d{1,2}[-\/\.]\d{1,4}(?:\s+\d{1,2}:\d{1,2}(:\d{1,2})?)?$/', $value);
    }

    private function normalizeDate(string $value): ?string
    {
        try {
            $date = Carbon::parse($value);

            return $date->format($date->hour === 0 && $date->minute === 0 && $date->second === 0 ? 'Y-m-d' : 'Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function prepare(array $row, array $fields): array
    {
        $result = [];

        foreach ($fields as $dbField => $excelField) {
            $value = $row[$excelField] ?? null;

            if ($dbField === 'trigger_date' && is_array($value)) {
                $value = json_encode($value);
            }

            if (is_string($value) && $this->looksLikeDate($value)) {
                $value = $this->normalizeDate($value);
            }

            $result[$dbField] = $value;
        }

        $result['import_to_sales'] = 0;

        return $result;
    }
}
