<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MGroup extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_groups';
    protected $fillable = ['course_id', 'name', 'idnumber', 'enrolmentkey', 'picture', 'description'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function members()
    {
        return $this->hasMany(MGroupMember::class, 'group_id');
    }

    public function groupings()
    {
        return $this->belongsToMany(MGrouping::class, 'm_groupings_groups', 'group_id', 'grouping_id');
    }
}
