<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    // Colonnes autorisées en écriture de masse
    protected $fillable = ['conversation_id','role','content'];

    // Chaque fois qu’un message est créé/modifié, on met à jour la conversation associée
    protected $touches = ['conversation'];

    // Relation : un message appartient à une conversation
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
