<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['code', 'name', 'level', 'department_id', 'duration_years', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function modes()
    {
        return $this->hasMany(ProgramMode::class, 'program_id');
    }

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function feeStructures()
    {
        return $this->hasMany(FeeStructure::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }
}
