<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClubRaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'club_name' => ['nullable', 'string', 'max:255'],
            'release_point' => ['nullable', 'string', 'max:255'],
            'distance' => ['nullable', 'numeric', 'min:0'],
            'distance_unit' => ['nullable', 'string', 'in:km,miles'],
            'race_date' => ['nullable', 'date'],
            'release_time' => ['nullable', 'date_format:H:i'],
            'description' => ['nullable', 'string'],
            'weather_conditions' => ['nullable', 'string', 'max:255'],
            'wind_direction' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:upcoming,completed,cancelled'],
        ];
    }
}
