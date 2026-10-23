<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'ip_address', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
