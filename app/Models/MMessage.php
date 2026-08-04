<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MMessage extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_messages';
    protected $fillable = ['conversation_id', 'user_id', 'body', 'reply_to'];

    public function conversation()
    {
        return $this->belongsTo(MMessageConversation::class, 'conversation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function replyTo()
    {
        return $this->belongsTo(MMessage::class, 'reply_to');
    }
}
