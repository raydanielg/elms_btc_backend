<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;
    protected $fillable = ['actor_id', 'action', 'entity', 'entity_id', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
