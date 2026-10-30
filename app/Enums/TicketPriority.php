<?php

namespace App\Enums;

enum TicketPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Low',
            self::MEDIUM => 'Medium',
            self::HIGH => 'High',
            self::CRITICAL => 'Critical',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::LOW => 'bg-slate-100 text-slate-700 border-slate-300',
            self::MEDIUM => 'bg-blue-100 text-blue-800 border-blue-300',
            self::HIGH => 'bg-amber-100 text-amber-800 border-amber-300',
            self::CRITICAL => 'bg-red-100 text-red-800 border-red-300 font-semibold',
        };
    }
}

