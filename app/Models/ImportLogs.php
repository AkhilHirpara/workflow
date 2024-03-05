<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportLogs extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_id',
        'import_status',
        'response',
    ];

}
