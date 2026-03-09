<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineComment extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'pipeline_comments';

    protected $fillable = [
        'pipeline_lead_status_id',
        'user_id',
        'comment_parent_id',
        'comment',
        'status',
        'path',
    ];

    public function pipelineLeadStatus(): BelongsTo
    {
        return $this->belongsTo(PipelineLeadStatus::class, 'pipeline_lead_status_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PipelineComment::class, 'comments_parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(PipelineComment::class, 'comments_parent_id');
    }

    public function getAwsPathAttribute()
    {
        if ($this->path) {
            return s3_getTempUrl(config('app.domain_name').'/'.$this->path);
        }

        return null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
