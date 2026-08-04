<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MUserInfoField extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_user_info_field';
    protected $fillable = [
        'category_id', 'shortname', 'name', 'datatype', 'description',
        'defaultdata', 'param1', 'sortorder', 'required', 'locked', 'visible', 'signup',
    ];

    protected $casts = [
        'required' => 'boolean',
        'locked' => 'boolean',
        'visible' => 'boolean',
        'signup' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(MUserInfoCategory::class, 'category_id');
    }

    public function data()
    {
        return $this->hasMany(MUserInfoData::class, 'field_id');
    }

    /**
     * Get menu options if datatype is menu.
     */
    public function getMenuOptions(): array
    {
        if ($this->datatype !== 'menu' || !$this->param1) return [];
        return array_map('trim', explode(',', $this->param1));
    }
}
