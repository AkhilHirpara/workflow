<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FilesShared extends Model
{
    use HasFactory;

    protected $table = 'files_shared';


    protected $fillable = [
        'file_id',
        'user_id',
        'shared_by',
        'only_file_shared',
        'permission_assigned',
        'is_own',
    ];

    public function userDetails()
    {
        return $this->hasMany(User::class, 'id', 'user_id');
    }

}
