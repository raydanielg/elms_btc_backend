<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MMessageConversation extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_message_conversations';
    protected $fillable = ['name', 'is_group', 'created_by'];

    protected $casts = ['is_group' => 'boolean'];

    public function members()
    {
        return $this->hasMany(MMessageConversationMember::class, 'conversation_id');
    }

    public function messages()
    {
        return $this->hasMany(MMessage::class, 'conversation_id')->orderBy('created_at');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
