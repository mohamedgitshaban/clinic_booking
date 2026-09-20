<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BookingConfirmed extends Notification
{
    use Queueable;

    public function __construct(private readonly Booking $booking) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'doctor' => $this->booking->doctor->name,
            'date' => $this->booking->date->format('Y-m-d'),
            'time' => substr($this->booking->start_time, 0, 5),
            'message' => 'Booking confirmed successfully.',
        ];
    }
}
