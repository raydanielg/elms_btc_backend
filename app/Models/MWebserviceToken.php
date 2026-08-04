<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MWebserviceToken extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_webservice_tokens';
    protected $fillable = ['user_id', 'token', 'name', 'service', 'iprestriction', 'validuntil', 'lastaccess', 'active'];

    protected $casts = ['active' => 'boolean', 'validuntil' => 'datetime', 'lastaccess' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
