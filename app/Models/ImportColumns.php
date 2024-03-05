<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportColumns extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_id',
        'project_id',
        'column_heading',
        'status',
        'review_status',
    ];
}
