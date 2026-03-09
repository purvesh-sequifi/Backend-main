<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExcelImportHistory extends Model
{
    use HasFactory;

    public const PHASE_SALE_IMPORT = 'sale_import';
    public const PHASE_SALE_PROCESSING = 'sale_processing';

    public static function resolvePhaseMessage(int $status, ?string $currentPhase): string
    {
        $currentPhase = is_string($currentPhase) ? trim($currentPhase) : null;
        $currentPhase = ($currentPhase === '') ? null : $currentPhase;

        if ($status === 2) {
            return 'Failed';
        }

        if ($status === 0 && $currentPhase === null) {
            return 'Completed';
        }

        if ($currentPhase === self::PHASE_SALE_PROCESSING) {
            return 'Sale processing';
        }

        // Default / backward-compat
        return 'File uploading';
    }

    public static function resolveDisplayProgress(
        int $status,
        ?string $currentPhase,
        ?float $phaseProgress,
        float $rowPct,
        bool $saleProcessingFallbackToRowPct,
    ): int {
        $currentPhase = is_string($currentPhase) ? trim($currentPhase) : null;
        $currentPhase = ($currentPhase === '') ? null : $currentPhase;

        $pct = $rowPct;

        if ($status === 0 && $currentPhase === null) {
            $pct = 100.0;
        } elseif ($currentPhase === self::PHASE_SALE_PROCESSING) {
            if ($phaseProgress !== null) {
                $pct = $phaseProgress;
            } else {
                $pct = $saleProcessingFallbackToRowPct ? $rowPct : 0.0;
            }
        }

        return (int) round((float) max(0, min(100, $pct)));
    }

    protected $table = 'excel_import_history';

    protected $fillable = [
        'id',
        'user_id',
        'uploaded_file',
        'new_records',
        'updated_records',
        'error_records',
        'total_records',
        'status',
        'current_phase',
        'phase_progress',
        'template_id',
        'errors',
        'created_at',
        'updated_pids',
        'new_pids',
        'error_pids',
    ];

    protected $hidden = [
        'updated_at',
    ];

    protected $casts = [
        'created_at' => 'string',
        'phase_progress' => 'float',
        'updated_pids' => 'array',
        'new_pids' => 'array',
        'error_pids' => 'array',
    ];

    public $timestamps = false;

    public function users(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name');
    }

    public function legacyHistory(): HasOne
    {
        return $this->hasOne(LegacyApiRawDataHistory::class, 'excel_import_id', 'id');
    }
}
