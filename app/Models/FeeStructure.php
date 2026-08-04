<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FeeStructure extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['program_id', 'mode', 'item', 'amount', 'academic_year'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}
