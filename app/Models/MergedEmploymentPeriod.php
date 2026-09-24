<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MergedEmploymentPeriod extends Model
{
    public $timestamps = false;

    protected $table = 'merged_employment_periods';

    protected $fillable = ['emp_id', 'project_id', 'date_from', 'date_to'];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
    ];
}
