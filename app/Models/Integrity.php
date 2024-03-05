<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Integrity extends Model
{
    use HasFactory;

    protected $table = 'integrity';

    protected $fillable = [
        'project_id',
        'row_id',
        'answers',
        'status',
        'worked_by',
        'record_owner',
        'last_modified_by',
        'worked_date',
        'last_review_status',
        'last_review_doneby',
        'last_review_check_date',
    ];

    public function workedBy()
    {
        return $this->hasOne(User::class, 'id', 'worked_by');
    }
    
    public function lastReviewDoneBy()
    {
        return $this->hasOne(User::class, 'id', 'last_review_doneby');
    }

}
