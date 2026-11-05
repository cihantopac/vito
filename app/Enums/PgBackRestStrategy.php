<?php

namespace App\Enums;

use App\Contracts\VitoEnum;

enum PgBackRestStrategy: string implements VitoEnum
{
    case STANDARD = 'standard';
    case CUSTOM = 'custom';

    public function getColor(): string
    {
        return 'info';
    }

    public function getText(): string
    {
        return match ($this) {
            self::STANDARD => 'Standard',
            self::CUSTOM => 'Custom',
        };
    }

    public static function standard(): array
    {
        return [
            'schedules' => ['full' => '0 2 * * 0', 'diff' => '0 2 * * 1-6', 'incr' => '0 * * * *'],
            'retention' => ['full' => 4, 'diff' => 7],
            'verify_schedule' => '0 4 * * 6',
            'check_schedule' => '30 3 * * *',
        ];
    }
}
