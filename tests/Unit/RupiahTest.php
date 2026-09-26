<?php

namespace Tests\Unit;

use App\Support\Rupiah;
use PHPUnit\Framework\TestCase;

class RupiahTest extends TestCase
{
    public function test_it_formats_rupiah_with_dot_separators(): void
    {
        $this->assertSame('Rp 15.000.000', Rupiah::format(15000000));
        $this->assertSame('-Rp 2.500', Rupiah::format(-2500));
    }

    public function test_it_rejects_float_input(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Rupiah::parse(15.5);
    }
}
