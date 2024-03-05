<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Templates;

class Questions extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'export_heading',
        'comment_required',
        'question',
        'choices',
        'status',
        'template_id',
        'order_no',
        'created_by',
    ];

    public function getChoicesAttribute($value)
    {
        return json_decode($value);
    }

    public function getTemplateDetails($value)
    {
        return $this->hasOne(Templates::class, 'id', 'template_id');
    }

}
