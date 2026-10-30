<?php

namespace App\Services;

use App\Models\TicketSequence;
use Illuminate\Support\Facades\DB;

class TicketIdGenerator
{
    /**
     * Generate the next unique ticket ID for the current year.
     * Uses pessimistic locking to prevent race conditions during concurrent ticket creations.
     *
     * Format: TKT-YYYY-000001
     */
    public function generate(?int $year = null): string
    {
        $year = $year ?? (int) now()->format('Y');

        return DB::transaction(function () use ($year) {
            $sequence = TicketSequence::where('year', $year)->lockForUpdate()->first();

            if (! $sequence) {
                // First ticket in this calendar year
                $sequence = TicketSequence::create([
                    'year' => $year,
                    'last_number' => 0,
                ]);
                $sequence = TicketSequence::where('year', $year)->lockForUpdate()->first();
            }

            $nextNumber = $sequence->last_number + 1;
            $sequence->update(['last_number' => $nextNumber]);

            return sprintf('TKT-%d-%06d', $year, $nextNumber);
        });
    }
}

