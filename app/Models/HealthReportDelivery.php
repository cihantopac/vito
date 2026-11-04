<?php

namespace App\Models;

class HealthReportDelivery extends AbstractModel
{
    protected $fillable = ['user_id', 'report_date', 'status'];
}
