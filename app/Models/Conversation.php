<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['user_id','title','meta'];
    protected $casts = ['meta' => 'array'];

    public function user()     { return $this->belongsTo(User::class); }
    public function messages() { return $this->hasMany(Message::class); }
}
