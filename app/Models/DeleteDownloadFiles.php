<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class DeleteDownloadFiles extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'file_id',
        'status',
    ];

    public function UserDetails()
    {
        return $this->hasMany(User::class, 'id', 'user_id');
    }

    public function FileDetails()
    {
        return $this->hasOne(Files::class, 'id', 'file_id');
    }
    
}
