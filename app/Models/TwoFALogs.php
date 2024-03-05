<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TwoFALogs extends Model
{
    use HasFactory;

    protected $table = 'twofalogs';

    protected $fillable = [
        'user_id',
        'twofa_code',
        'email_sent',
        'email_sent_time',
        'twofa_verified',
        'twofa_verified_time'
    ];
}
