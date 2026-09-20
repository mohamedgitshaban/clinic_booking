<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\BookingActionNotAllowedException;
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

            return $booking->load('services')->setRelation('doctor', $doctor);
        });
    }

    /**
     * Cancel a booking, enforcing that a completed booking can't be
     * cancelled and that cancellation happens outside the configured
     * cutoff window before the appointment.
     */
    public function cancel(Booking $booking): Booking
    {
        if ($booking->status === BookingStatus::Completed) {
            throw BookingActionNotAllowedException::because('A completed booking cannot be cancelled.');
        }

        if ($booking->status === BookingStatus::Cancelled) {
            throw BookingActionNotAllowedException::because('This booking is already cancelled.');
        }

        $appointmentAt = Carbon::parse($booking->date->format('Y-m-d').' '.$booking->start_time);
        $cutoffHours = (int) config('booking.cancellation_hours');

        if (now()->addHours($cutoffHours)->greaterThan($appointmentAt)) {
            throw BookingActionNotAllowedException::because(
                "Bookings can only be cancelled at least {$cutoffHours} hour(s) before the appointment."
            );
        }

        $booking->update(['status' => BookingStatus::Cancelled]);

        return $booking;
    }

    /**
     * Move a booking to a new date/time, re-validating availability (the
     * booking's own current slot is excluded from that check) under the
     * same lock + unique-constraint protection used when first booking.
     */
    public function reschedule(Booking $booking, string $date, string $startTime): Booking
    {
        if (in_array($booking->status, [BookingStatus::Completed, BookingStatus::Cancelled], true)) {
            throw BookingActionNotAllowedException::because('This booking cannot be rescheduled.');
        }

        return DB::transaction(function () use ($booking, $date, $startTime) {
            $doctor = $booking->doctor;

            $doctor->bookings()
                ->where('id', '!=', $booking->id)
                ->whereDate('date', $date)
                ->lockForUpdate()
                ->get();

            $durationMinutes = (int) $booking->services->sum('pivot.duration');
            $start = Carbon::parse($startTime);

            $slotIsAvailable = in_array(
                $start->format('H:i'),
                $this->availability->getAvailableSlots($doctor, Carbon::parse($date), $durationMinutes, $booking->id),
                true
            );

            if (! $slotIsAvailable) {
                throw SlotUnavailableException::forSlot();
            }

            try {
                $booking->update([
                    'date' => $date,
                    'start_time' => $start->format('H:i:s'),
                    'end_time' => $start->copy()->addMinutes($durationMinutes)->format('H:i:s'),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw SlotUnavailableException::forSlot();
            }

            return $booking->fresh(['doctor', 'services']);
        });
    }
}
