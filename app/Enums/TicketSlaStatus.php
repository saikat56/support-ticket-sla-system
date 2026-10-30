<?php

namespace App\Enums;

enum TicketSlaStatus: string
{
    case WITHIN_SLA = 'within_sla';
    case APPROACHING = 'approaching';
    case BREACHED = 'breached';

    public function label(): string
    {
        return match ($this) {
            self::WITHIN_SLA => 'Within SLA',
            self::APPROACHING => 'Approaching SLA Breach',
            self::BREACHED => 'SLA Breached',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::WITHIN_SLA => 'bg-emerald-100 text-emerald-800 border-emerald-300',
            self::APPROACHING => 'bg-amber-100 text-amber-800 border-amber-300 animate-pulse',
            self::BREACHED => 'bg-rose-100 text-rose-800 border-rose-300 font-bold',
        };
    }
}

