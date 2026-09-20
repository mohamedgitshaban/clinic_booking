<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Booking Cancellation Window
    |--------------------------------------------------------------------------
    |
    | The minimum number of hours before an appointment's start time that a
    | user is still allowed to cancel it.
    |
    */

    'cancellation_hours' => (int) env('BOOKING_CANCELLATION_HOURS', 24),

];
