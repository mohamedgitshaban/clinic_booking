<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Doctor;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly BookingPriceCalculator $calculator,
    ) {}

    /**
     * Create a booking inside a transaction, re-validating availability under
     * a row lock so two concurrent requests cannot double-book the same slot.
     * The database's unique `slot_key` constraint is the final line of
     * defense for the exact-slot race that a lock alone cannot catch.
     *
     * @param  Collection<int, Service>  $services
     */
    public function book(User $user, Doctor $doctor, Collection $services, string $date, string $startTime): Booking
    {
        return DB::transaction(function () use ($user, $doctor, $services, $date, $startTime) {
            // Serializes concurrent attempts against this doctor's existing bookings for the date.
            $doctor->bookings()->whereDate('date', $date)->lockForUpdate()->get();

            $totals = $this->calculator->calculate($services);
            $start = Carbon::parse($startTime);

            $slotIsAvailable = in_array(
                $start->format('H:i'),
                $this->availability->getAvailableSlots($doctor, Carbon::parse($date), $totals['total_duration']),
                true
            );

            if (! $slotIsAvailable) {
                throw SlotUnavailableException::forSlot();
            }

            try {
                $booking = Booking::create([
                    'user_id' => $user->id,
                    'doctor_id' => $doctor->id,
                    'date' => $date,
                    'start_time' => $start->format('H:i:s'),
                    'end_time' => $start->copy()->addMinutes($totals['total_duration'])->format('H:i:s'),
                    'total_price' => $totals['total_price'],
                    'status' => BookingStatus::Confirmed,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw SlotUnavailableException::forSlot();
            }

            $booking->services()->attach(
                $services->mapWithKeys(fn (Service $service): array => [
                    $service->id => ['price' => $service->price, 'duration' => $service->duration],
                ])
            );

            return $booking->load('services');
        });
    }
}
