<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    public function users()
    {
        //return $this->belongsToMany(User::class, 'user_permissions');
        return $this->belongsToMany(User::class, 'user_permissions')
            ->withPivot('active')
            ->withTimestamps();
    }
}
