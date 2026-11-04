<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;

class HealthReportSnapshot extends AbstractModel
{
    protected $fillable = ['project_id', 'hour', 'issues'];

    protected $casts = ['project_id' => 'integer', 'hour' => 'immutable_datetime', 'issues' => 'array'];
}
