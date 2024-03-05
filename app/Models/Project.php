<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProjectsUsers;
use App\Models\Questions;
use App\Models\Templates;
use App\Models\Files;
use App\Models\ImportColumns;
use App\Models\Investor;
use App\Models\Platform;

class Project extends Model
{
    use HasFactory;
    // protected $appends = ['projectfile'];

    protected $fillable = [
        'project_name',
        'identifier',
        'investor_id',
        'platform_id',
        'template_id',
        'percentage_completed',
        'integrity_precentage_completed',
        'status',
        'import_time_taken',
        'last_completed_step',
        'file_completeness',
        'only_integrity',
        'only_review',
        'is_archived',
        'pll_flag',
        'created_by',
    ];

    public function relatedUsers()
    {
        return $this->hasMany(ProjectsUsers::class, 'project_id', 'id');
    }


    public function relatedAllQuestions()
    {
        return $this->hasMany(Questions::class, 'template_id', 'template_id');
    }

    public function relatedQuestions()
    {
        return $this->relatedAllQuestions()->where('status', '=', 1);
    }

    public function relatedDeletedQuestions()
    {
        return $this->relatedAllQuestions()->where('status', '=', 0);
    }
    
    public function relatedFileColumns()
    {
        return $this->hasMany(ImportColumns::class, 'project_id', 'id');
    }


    public function Templatedetails()
    {
        return $this->hasOne(Templates::class, 'id', 'template_id');
    }

    public function Projectfile()
    {
        return $this->hasOne(Files::class, 'project_id', 'id');
    }

    public function Investordetails()
    {
        return $this->hasOne(Investor::class, 'id', 'investor_id');
    }

    public function Platformdetails()
    {
        return $this->hasOne(Platform::class, 'id', 'platform_id');
    }

    // public function getProjectfileAttribute()
    // {
    //     $find_data = Files::where('project_id',$this->id)->where('type', 'Import')->first();
    //     return ($find_data) ? $find_data : [];
    // }
}
