<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'doctor' => $this->doctor->name,
            'services' => $this->services->pluck('name'),
            'total_price' => $this->total_price,
            'date' => $this->date->format('Y-m-d'),
            'time' => substr($this->start_time, 0, 5),
            'status' => $this->status->value,
        ];
    }
}
