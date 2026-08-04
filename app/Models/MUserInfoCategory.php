<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MUserInfoCategory extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_user_info_category';
    protected $fillable = ['name', 'sortorder'];

    public function fields()
    {
        return $this->hasMany(MUserInfoField::class, 'category_id')->orderBy('sortorder');
    }
}
