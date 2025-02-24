<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pet extends Model
{
    protected $fillable = ['user_id', 'name', 'type', 'breed', 'birthdate', 'avatar', 'location'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
