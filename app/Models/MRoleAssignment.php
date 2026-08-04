<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MRoleAssignment extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_role_assignments';
    public $timestamps = false;

    protected $fillable = ['role_id', 'context_id', 'user_id', 'timemodified', 'modifier_id'];

    public function role()
    {
        return $this->belongsTo(MRole::class, 'role_id');
    }

    public function context()
    {
        return $this->belongsTo(MContext::class, 'context_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
