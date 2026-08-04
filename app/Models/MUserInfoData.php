<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MUserInfoData extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_user_info_data';
    protected $fillable = ['user_id', 'field_id', 'data'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function field()
    {
        return $this->belongsTo(MUserInfoField::class, 'field_id');
    }
}
