<?php

namespace App\Http\Requests\Concerns;

use App\Models\Doctor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared doctor_id/service_ids/date/time rules for BookingPreviewRequest and
 * StoreBookingRequest: both need to validate the same selection before
 * computing a price or creating a booking from it.
 */
trait ValidatesBookingSelection
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'doctor_id' => ['required', 'integer', 'exists:doctors,id'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['doctor_id', 'service_ids', 'service_ids.*'])) {
                    return;
                }

                $doctor = Doctor::find($this->input('doctor_id'));

                if (! $doctor?->offersServices($this->input('service_ids', []))) {
                    $validator->errors()->add('service_ids', 'One or more selected services are not offered by this doctor.');
                }
            },
        ];
    }
}
