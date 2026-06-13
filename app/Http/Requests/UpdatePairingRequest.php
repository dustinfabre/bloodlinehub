<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePairingRequest extends FormRequest
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
            'pair_name' => ['nullable', 'string', 'max:255'],
            'breeding_location_id' => [
                'nullable',
                'integer',
                \Illuminate\Validation\Rule::exists('locations', 'id')->where(fn ($q) => $q->where('user_id', $this->user()?->id ?? 0)),
            ],
        ];
    }
}
