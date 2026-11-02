<?php

namespace App\Models;

class HealthReportSnapshot extends AbstractModel
{
    protected $fillable = ['project_id', 'hour', 'issues'];

    protected $casts = ['hour' => 'immutable_datetime', 'issues' => 'array'];
}
