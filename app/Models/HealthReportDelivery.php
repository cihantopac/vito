<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HealthReportDeliveryStatus;

class HealthReportDelivery extends AbstractModel
{
    protected $fillable = ['user_id', 'report_date', 'status'];

    protected $casts = ['user_id' => 'integer', 'status' => HealthReportDeliveryStatus::class];
}
