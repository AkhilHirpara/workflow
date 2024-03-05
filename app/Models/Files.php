<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Project;
use App\Models\User;
use App\Models\FilesShared;

class Files extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'original_filename',
        'filename',
        'folder_id',
        'type',
        'parent_fileid',
        'total_rows',
        'imported_rows',
        'import_status',
        'import_start_time',
        'import_end_time',
        'status',
        'note',
        'created_by',
        'is_nested_upload',
    ];

    public function getTypeAttribute($value)
    {
        if ($value == 'Import')
            return 'Project Import';
        elseif ($value == 'Export')
            return 'Project Export';
        else
            return 'Personal';
    }

    public function ProjectDetails()
    {
        return $this->hasOne(Project::class, 'id', 'project_id');
    }

    public function UserDetails()
    {
        return $this->hasOne(User::class, 'id', 'created_by');
    }

    public function SharedDetails()
    {
        return $this->hasMany(FilesShared::class, 'file_id', 'id');
    }

    // public function SharedWith()
    // {
    //     return $this->hasOne(Project::class, 'id', 'project_id');
    // }
}
