<?php

namespace App\Models;

// use AWS\CRT\Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BucketSubTask extends Model
{
    use HasFactory;

    protected $table = 'bucket_subtask';

    // public $search;

    protected $fillable = [
        'id',
        'bucket_id',
        'name',
        'created_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
