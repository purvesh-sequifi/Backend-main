<?php

namespace App\Services\Csv;

use SplFileObject;

final class CsvRowStreamer
{
    private string $filePath;

    private string $delimiter;

    private string $enclosure;

    public function __construct(string $filePath, ?string $delimiter = null, string $enclosure = '"')
    {
        $this->filePath = $filePath;
        $this->enclosure = $enclosure;
        $this->delimiter = $delimiter ?? $this->detectDelimiter();
    }

    public function detectDelimiter(array $candidates = [',', ';', "\t", '|']): string
    {
        if (! is_readable($this->filePath)) {
            return ',';
        }

        $fileHandle = fopen($this->filePath, 'r');
        if ($fileHandle === false) {
            return ',';
        }

        $firstNonEmptyLine = '';
        while (($line = fgets($fileHandle)) !== false) {
            if (trim($line) !== '') {
                $firstNonEmptyLine = $line;
                break;
            }
        }
        fclose($fileHandle);

        if ($firstNonEmptyLine === '') {
            return ',';
        }

        $selectedDelimiter = ',';
        $bestSplitCount = -1;

        foreach ($candidates as $candidate) {
            $pattern = '/'.preg_quote($candidate, '/').'(?=(?:[^"]*"[^"]*")*[^"]*$)/';
            $splitCount = preg_match_all($pattern, $firstNonEmptyLine);

            if ($splitCount > $bestSplitCount) {
                $bestSplitCount = $splitCount;
                $selectedDelimiter = $candidate;
            }
        }

        return $selectedDelimiter;
    }

    public function stream(callable $onRow): void
    {
        $file = new SplFileObject($this->filePath);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $file->setCsvControl($this->delimiter, $this->enclosure);

        $headers = null;
        $dataRowIndex = 0;

        foreach ($file as $row) {
            if ($row === null || $row === [null] || $row === false) {
                continue;
            }

            $row = $this->normalizeRow($row);

            if ($this->isCompletelyEmptyRow($row)) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map('trim', $row);
                if (! empty($headers)) {
                    $headers[0] = $this->stripUtf8Bom($headers[0]);
                }

                continue;
            }

            $dataRowIndex++;
            $assoc = $this->combineRowWithHeaders($headers, $row);

            $onRow($assoc, $dataRowIndex);
        }
    }

    private function normalizeRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $value) {
            if ($value === null) {
                $normalized[] = null;

                continue;
            }
            $normalized[] = is_string($value) ? trim($value) : trim((string) $value);
        }

        return $normalized;
    }

    private function isCompletelyEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    private function combineRowWithHeaders(array $headers, array $row): array
    {
        $assoc = [];
        $max = max(count($headers), count($row));
        for ($i = 0; $i < $max; $i++) {
            $key = $headers[$i] ?? "col_{$i}";
            $assoc[$key] = $row[$i] ?? null;
        }

        return $assoc;
    }

    private function stripUtf8Bom(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_replace('/^\xEF\xBB\xBF/', '', $value);
    }

    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    public function getEnclosure(): string
    {
        return $this->enclosure;
    }

    public function getRowCount(): int
    {
        $file = new SplFileObject($this->filePath);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $file->setCsvControl($this->delimiter, $this->enclosure);

        $rowCount = 0;
        $headersFound = false;

        foreach ($file as $row) {
            if ($row === null || $row === [null] || $row === false) {
                continue;
            }

            $row = $this->normalizeRow($row);

            if ($this->isCompletelyEmptyRow($row)) {
                continue;
            }

            if (! $headersFound) {
                $headersFound = true;

                continue; // Skip header row
            }

            $rowCount++;
        }

        return $rowCount;
    }
}
