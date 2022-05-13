<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Option extends Model
{
    public function user_option()
    {
        return $this->hasMany(UserOption::class, 'option_id', 'id');
    }
}
