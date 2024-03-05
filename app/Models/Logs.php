<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Logs extends Model
{
    use HasFactory;

    protected $fillable = [
        'log_type',
        'module',
        'message',
        'created_by',
    ];

    public function UserDetails()
    {
        return $this->hasOne(User::class, 'id', 'created_by');
    }
}
