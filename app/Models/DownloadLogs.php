<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Files;

class DownloadLogs extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_id',
        'download_date',
        'downloaded_by',
        'delete_status',
        'delete_date',
    ];

    public function FileDetails()
    {
        return $this->hasOne(Files::class, 'id', 'file_id');
    }

    public function UserDetails()
    {
        return $this->hasOne(User::class, 'id', 'downloaded_by');
    }

}
