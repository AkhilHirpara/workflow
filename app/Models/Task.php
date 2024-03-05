<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $table = 'task';

    protected $fillable = [
        'project_id',
        'row_id',
        'grade',
        'comment',
        'answers',
        'status',
        'worked_by',
        'record_owner',
        'last_modified_by',
        'worked_date',
        'last_review_status',
        'last_review_doneby',
        'last_review_check_date',
        'ownership',
    ];

    public function workedBy()
    {
        return $this->hasOne(User::class, 'id', 'worked_by');
    }
    
    public function lastReviewDoneBy()
    {
        return $this->hasOne(User::class, 'id', 'last_review_doneby');
    }
    
    public function ownershipDetails()
    {
        return $this->hasOne(User::class, 'id', 'ownership');
    }


}
