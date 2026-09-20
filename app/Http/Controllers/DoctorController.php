<?php

namespace App\Http\Controllers;

use App\Http\Resources\DoctorResource;
use App\Http\Resources\ServiceResource;
use App\Models\Doctor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
}
