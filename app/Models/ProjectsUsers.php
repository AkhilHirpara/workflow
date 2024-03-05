<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ProjectsUsers extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
    ];

    public function getUser()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}
