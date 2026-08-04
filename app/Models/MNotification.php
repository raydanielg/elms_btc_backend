<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MNotification extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_notifications';
    protected $fillable = ['user_id', 'component', 'event_type', 'subject', 'body', 'context_url', 'read', 'read_at'];

    protected $casts = ['read' => 'boolean', 'read_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
