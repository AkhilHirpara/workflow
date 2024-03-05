<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShortcutManagement extends Model
{
    use HasFactory;
    protected $table = 'shortcut_management';

    protected $fillable = [
        'shortcut_url',
        'shortcut_name',
        'authentication_token',
        'created_by'
    ];
}
