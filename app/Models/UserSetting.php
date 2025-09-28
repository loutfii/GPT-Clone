<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    protected $fillable = [
        'user_id',
        // nouvelles colonnes
        'tone',
        'style',
        'context',
        'custom_system',
        // on garde preferences si tu veux encore l’utiliser quelque part
        'preferences',
    ];

    protected $casts = [
        'preferences' => 'array', // optionnel désormais
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
