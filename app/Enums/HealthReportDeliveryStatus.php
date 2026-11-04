<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\VitoEnum;
use App\Enums\Concerns\TextIsValue;

enum HealthReportDeliveryStatus: string implements VitoEnum
{
    use TextIsValue;

    case PENDING = 'pending';
    case SENDING = 'sending';
    case SENT = 'sent';
    case FAILED = 'failed';

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING, self::SENDING => 'warning',
            self::SENT => 'success',
            self::FAILED => 'danger',
        };
    }
}
