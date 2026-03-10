<?php

namespace App\Http\Requests;

use App\Models\Pairing;
use Illuminate\Foundation\Http\FormRequest;

class StorePairingRequest extends FormRequest
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
            'sire_id' => ['required', 'exists:pigeons,id'],
            'dam_id' => ['required', 'exists:pigeons,id', 'different:sire_id'],
            'pair_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->sire_id && $this->dam_id) {
                $exists = Pairing::where('user_id', $this->user()->id)
                    ->where('sire_id', $this->sire_id)
                    ->where('dam_id', $this->dam_id)
                    ->where('status', 'active')
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('dam_id', 'An active pairing with this sire and dam already exists.');
                }
            }
        });
    }
}
