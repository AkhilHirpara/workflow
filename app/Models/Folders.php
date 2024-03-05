<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\FoldersShared;

class Folders extends Model
{
    use HasFactory;
    public $table = "folders";
    protected $fillable = [
        'foldername',
        'folderpath',
        'parent_folder_id',
        'created_by',
    ];

    public function UserDetails()
    {
        return $this->hasOne(User::class, 'id', 'created_by');
    }

}
