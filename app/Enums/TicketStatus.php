<?php

namespace App\Enums;

enum TicketStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case WAITING_CUSTOMER = 'waiting_customer';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::IN_PROGRESS => 'In Progress',
            self::WAITING_CUSTOMER => 'Waiting for Customer',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::OPEN => 'bg-emerald-100 text-emerald-800 border-emerald-300',
            self::IN_PROGRESS => 'bg-indigo-100 text-indigo-800 border-indigo-300',
            self::WAITING_CUSTOMER => 'bg-yellow-100 text-yellow-800 border-yellow-300',
            self::RESOLVED => 'bg-teal-100 text-teal-800 border-teal-300',
            self::CLOSED => 'bg-gray-100 text-gray-700 border-gray-300',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::RESOLVED, self::CLOSED], true);
    }
}

