<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Doctor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{
    /**
     * Generate the available start times (H:i) for a doctor on a given date,
     * for a booking that needs $durationMinutes, excluding slots that would
     * overlap an existing (non-cancelled) booking.
     *
     * Pass $excludingBookingId when checking availability for a booking that
     * is itself being rescheduled, so its own current slot isn't treated as
     * occupied.
     *
     * @return array<int, string>
     */
    public function getAvailableSlots(Doctor $doctor, Carbon $date, int $durationMinutes, ?int $excludingBookingId = null): array
    {
        if ($durationMinutes <= 0) {
            return [];
        }

        $schedule = $doctor->schedules()
            ->available()
            ->where('day_of_week', $date->dayOfWeek)
            ->first();

        if (! $schedule) {
            return [];
        }

        $bookedRanges = $doctor->bookings()
            ->whereDate('date', $date)
            ->where('status', '!=', BookingStatus::Cancelled)
            ->when($excludingBookingId, fn ($query, $id) => $query->where('id', '!=', $id))
            ->get(['start_time', 'end_time']);

        $scheduleEnd = Carbon::parse($schedule->end_time);
        $slotStart = Carbon::parse($schedule->start_time);

        $slots = [];

        while ($slotStart->copy()->addMinutes($durationMinutes)->lte($scheduleEnd)) {
            $slotEnd = $slotStart->copy()->addMinutes($durationMinutes);

            if (! $this->overlapsAny($slotStart, $slotEnd, $bookedRanges)) {
                $slots[] = $slotStart->format('H:i');
            }

            $slotStart = $slotStart->copy()->addMinutes($durationMinutes);
        }

        return $slots;
    }

    /**
     * @param  Collection<int, Booking>  $bookedRanges
     */
    private function overlapsAny(Carbon $slotStart, Carbon $slotEnd, Collection $bookedRanges): bool
    {
        foreach ($bookedRanges as $booking) {
            $bookedStart = Carbon::parse($booking->start_time);
            $bookedEnd = Carbon::parse($booking->end_time);

            if ($slotStart->lt($bookedEnd) && $bookedStart->lt($slotEnd)) {
                return true;
            }
        }

        return false;
    }
}
