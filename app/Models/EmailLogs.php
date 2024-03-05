<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailLogs extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject',
        'sent_on',
        'sent_to',
        'user_id',
        'delete_token',
        'is_deleted',
        // 'delete_date',
        'email_reminder_num',
    ];

}
