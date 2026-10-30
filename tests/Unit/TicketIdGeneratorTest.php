<?php

namespace Tests\Unit;

use App\Models\TicketSequence;
use App\Services\TicketIdGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketIdGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_sequential_formatted_ids(): void
    {
        $generator = new TicketIdGenerator();

        $id1 = $generator->generate(2026);
        $id2 = $generator->generate(2026);
        $id3 = $generator->generate(2026);

        $this->assertEquals('TKT-2026-000001', $id1);
        $this->assertEquals('TKT-2026-000002', $id2);
        $this->assertEquals('TKT-2026-000003', $id3);
    }

    public function test_sequence_resets_per_year(): void
    {
        $generator = new TicketIdGenerator();

        $id2026 = $generator->generate(2026);
        $id2027 = $generator->generate(2027);

        $this->assertEquals('TKT-2026-000001', $id2026);
        $this->assertEquals('TKT-2027-000001', $id2027);
    }
}

