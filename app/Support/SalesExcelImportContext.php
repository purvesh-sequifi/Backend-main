<?php

namespace App\Support;

final class SalesExcelImportContext
{
    private ?string $importKey = null;
    private ?string $importStartedAt = null;
    private ?int $initiatorUserId = null;

    /**
     * Throttle state for progress updates (keyed by excelId).
     *
     * @var array<int, array{ts:int, pct:float|null}>
     */
    private array $progressThrottleState = [];

    public function set(string $importKey, string $importStartedAt, int $initiatorUserId): void
    {
        $this->importKey = $importKey;
        $this->importStartedAt = $importStartedAt;
        $this->initiatorUserId = $initiatorUserId;
    }

    public function isSet(): bool
    {
        return is_string($this->importKey) && $this->importKey !== ''
            && is_string($this->importStartedAt) && $this->importStartedAt !== '';
    }

    public function importKey(): string
    {
        return (string) ($this->importKey ?? '');
    }

    public function importStartedAt(): string
    {
        return (string) ($this->importStartedAt ?? '');
    }

    public function initiatorUserId(): int
    {
        return (int) ($this->initiatorUserId ?? 0);
    }

    public function lastProgressTsMs(int $excelId): int
    {
        return (int) ($this->progressThrottleState[$excelId]['ts'] ?? 0);
    }

    public function lastProgressPct(int $excelId): ?float
    {
        $pct = $this->progressThrottleState[$excelId]['pct'] ?? null;

        return $pct === null ? null : (float) $pct;
    }

    public function setProgressThrottleState(int $excelId, int $tsMs, ?float $pct): void
    {
        $this->progressThrottleState[$excelId] = [
            'ts' => $tsMs,
            'pct' => $pct,
        ];
    }
}

