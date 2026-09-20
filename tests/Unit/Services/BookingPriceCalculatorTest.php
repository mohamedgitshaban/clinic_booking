<?php

namespace Tests\Unit\Services;

use App\Models\Service;
use App\Services\BookingPriceCalculator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class BookingPriceCalculatorTest extends TestCase
{
    public function test_it_sums_price_and_duration_across_services(): void
    {
        $services = new Collection([
            new Service(['price' => 300, 'duration' => 30]),
            new Service(['price' => 500, 'duration' => 60]),
        ]);

        $totals = (new BookingPriceCalculator)->calculate($services);

        $this->assertSame(800.0, $totals['total_price']);
        $this->assertSame(90, $totals['total_duration']);
    }

    public function test_it_returns_zero_totals_for_an_empty_selection(): void
    {
        $totals = (new BookingPriceCalculator)->calculate(new Collection);

        $this->assertSame(0.0, $totals['total_price']);
        $this->assertSame(0, $totals['total_duration']);
    }
}
