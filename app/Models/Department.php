<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['code', 'name', 'hod_id'];

    public function hod()
    {
        return $this->belongsTo(User::class, 'hod_id');
    }

    public function programs()
    {
        return $this->hasMany(Program::class);
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class);
    }
}
