<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserExcelImportHistory extends Model
{
    use HasFactory;

    protected $table = 'user_excel_import_histories';

    protected $fillable = [
        'id',
        'user_id',
        'uploaded_file',
        'total_records',
        'created_at',
    ];

    protected $hidden = [
        'updated_at',
    ];

    public function users(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name');
    }
}
