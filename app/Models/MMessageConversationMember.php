<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MMessageConversationMember extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_message_conversation_members';
    public $timestamps = false;

    protected $fillable = ['conversation_id', 'user_id', 'last_read_at'];

    protected $casts = ['last_read_at' => 'datetime'];

    public function conversation()
    {
        return $this->belongsTo(MMessageConversation::class, 'conversation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
