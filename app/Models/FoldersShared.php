<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Folders;

class FoldersShared extends Model
{
    use HasFactory;
    public $table = "folders_shared";

    protected $fillable = [
        'folder_id',
        'user_id',
        'shared_by',
        'only_folder_shared',
        'permission_assigned',
    ];

    public function userDetails()
    {
        return $this->hasMany(User::class, 'id', 'user_id');
    }

    // public function SharedDetails()
    // {
    //     return $this->hasMany(FilesShared::class, 'file_id', 'id');
    // }

    public function FolderDetails()
    {
        return $this->hasOne(Folders::class, 'id', 'folder_id');
    }
}
