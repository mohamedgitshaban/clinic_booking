<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesBookingSelection;
use Illuminate\Foundation\Http\FormRequest;

class BookingPreviewRequest extends FormRequest
{
    use ValidatesBookingSelection;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
