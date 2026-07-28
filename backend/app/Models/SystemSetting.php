<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemSetting extends Model
{
    use SoftDeletes;
    protected $fillable = ['key', 'value', 'type', 'is_secret', 'group', 'description'];

    protected function casts(): array
    {
        return ['is_secret' => 'boolean'];
    }
}
