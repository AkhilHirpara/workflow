<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Questions;

class Templates extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'created_by',
    ];

    public function relatedAllQuestions()
    {
        return $this->hasMany(Questions::class, 'template_id', 'id');
    }

    public function relatedQuestions()
    {
        return $this->relatedAllQuestions()->where('status', '=', 1)->orderBy('order_no');
    }

    public function relatedDeletedQuestions()
    {
        return $this->relatedAllQuestions()->where('status', '=', 0);
    }
}
