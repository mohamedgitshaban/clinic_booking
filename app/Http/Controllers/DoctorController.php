<?php

namespace App\Http\Controllers;

use App\Http\Requests\AvailableSlotsRequest;
use App\Http\Resources\DoctorResource;
use App\Http\Resources\ServiceResource;
use App\Models\Doctor;
use App\Services\AvailabilityService;
use App\Services\BookingPriceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class DoctorController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return DoctorResource::collection(
            Doctor::active()->orderBy('name')->get()
        );
    }

    public function services(Doctor $doctor): JsonResponse
    {
        $services = $doctor->services()->active()->orderBy('name')->get();

        return response()->json([
            'doctor' => [
                'id' => $doctor->id,
                'name' => $doctor->name,
            ],
            'services' => ServiceResource::collection($services),
        ]);
    }

    public function availableSlots(
        AvailableSlotsRequest $request,
        Doctor $doctor,
        AvailabilityService $availability,
        BookingPriceCalculator $calculator
    ): JsonResponse {
        $services = $doctor->resolveServices($request->validated('service_ids'));

        $totalDuration = $calculator->calculate($services)['total_duration'];
        $date = Carbon::parse($request->validated('date'));

        return response()->json([
            'date' => $date->format('Y-m-d'),
            'slots' => $availability->getAvailableSlots($doctor, $date, $totalDuration),
        ]);
    }
}
