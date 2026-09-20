<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingPreviewRequest;
use App\Http\Requests\RescheduleBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Doctor;
use App\Notifications\BookingConfirmed;
use App\Services\BookingPriceCalculator;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $bookings = $request->user()
            ->bookings()
            ->with(['doctor', 'services'])
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->get();

        return BookingResource::collection($bookings);
    }

    public function preview(BookingPreviewRequest $request, BookingPriceCalculator $calculator): JsonResponse
    {
        $doctor = Doctor::findOrFail($request->validated('doctor_id'));
        $services = $doctor->resolveServices($request->validated('service_ids'));

        $totals = $calculator->calculate($services);

        return response()->json([
            'doctor' => $doctor->name,
            'services' => $services->pluck('name'),
            'total_price' => $totals['total_price'],
            'date' => $request->validated('date'),
            'time' => $request->validated('time'),
        ]);
    }

    public function store(StoreBookingRequest $request, BookingService $bookingService): JsonResponse
    {
        $doctor = Doctor::findOrFail($request->validated('doctor_id'));
        $services = $doctor->resolveServices($request->validated('service_ids'));

        $booking = $bookingService->book(
            $request->user(),
            $doctor,
            $services,
            $request->validated('date'),
            $request->validated('time'),
        );

        $request->user()->notify(new BookingConfirmed($booking));

        return (new BookingResource($booking))
            ->response()
            ->setStatusCode(201);
    }

    public function cancel(Booking $booking, BookingService $bookingService): BookingResource
    {
        Gate::authorize('cancel', $booking);

        return new BookingResource($bookingService->cancel($booking));
    }

    public function reschedule(RescheduleBookingRequest $request, Booking $booking, BookingService $bookingService): BookingResource
    {
        $booking = $bookingService->reschedule(
            $booking,
            $request->validated('date'),
            $request->validated('time'),
        );

        return new BookingResource($booking);
    }
}
