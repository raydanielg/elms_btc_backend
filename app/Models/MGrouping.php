<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MGrouping extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_groupings';
    protected $fillable = ['course_id', 'name', 'description'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function groups()
    {
        return $this->belongsToMany(MGroup::class, 'm_groupings_groups', 'grouping_id', 'group_id');
    }
}
