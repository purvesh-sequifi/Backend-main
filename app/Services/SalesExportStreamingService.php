<?php

declare(strict_types=1);

namespace App\Services;

use ZipArchive;

class SalesExportStreamingService
{
    public function ensureExportDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public function openCsv(string $absolutePath): \SplFileObject
    {
        $fh = new \SplFileObject($absolutePath, 'w');
        $fh->setCsvControl(',');

        return $fh;
    }

    public function writeHeadings(\SplFileObject $fh, array $headings): void
    {
        $fh->fputcsv($headings);
    }

    public function writeRow(\SplFileObject $fh, array $row): void
    {
        $fh->fputcsv($row);
    }

    public function zipFiles(array $absoluteFiles, string $zipAbsolutePath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipAbsolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create ZIP archive: '.$zipAbsolutePath);
        }
        foreach ($absoluteFiles as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();
    }
}
