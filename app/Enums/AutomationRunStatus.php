<?php

namespace App\Enums;

enum AutomationRunStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Partial = 'partial';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Partial => 'Partially Completed',
        };
    }
}
