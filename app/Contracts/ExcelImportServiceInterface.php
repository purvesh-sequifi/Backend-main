<?php

namespace App\Contracts;

interface ExcelImportServiceInterface
{
    public function processExcelFiles(): array;

    public function getProcessedRecords(): array;

    public function saveProcessedRecords(array $records): void;

    public function resetProcessedRecords(): void;
}
