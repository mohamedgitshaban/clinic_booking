<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Support\Collection;

class BookingPriceCalculator
{
    /**
     * @param  Collection<int, Service>  $services
     * @return array{total_price: float, total_duration: int}
     */
    public function calculate(Collection $services): array
    {
        return [
            'total_price' => (float) $services->sum(fn (Service $service): float => (float) $service->price),
            'total_duration' => (int) $services->sum(fn (Service $service): int => (int) $service->duration),
        ];
    }
}
