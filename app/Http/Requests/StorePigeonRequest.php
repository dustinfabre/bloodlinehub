<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RequiresGoogleDriveForImages;
use App\Models\Pigeon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePigeonRequest extends FormRequest
{
    use RequiresGoogleDriveForImages;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure boolean fields have proper defaults when not provided
        $this->merge([
            'for_sale' => $this->boolean('for_sale'),
            'hide_price' => $this->boolean('hide_price'),
        ]);

        // Uppercase name and ring_number
        if ($this->has('name')) {
            $this->merge(['name' => strtoupper($this->input('name'))]);
        }
        if ($this->has('ring_number')) {
            $this->merge(['ring_number' => strtoupper($this->input('ring_number'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'in:male,female'],
            'hatch_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(Pigeon::STATUSES)],
            'bloodline' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'ring_number' => ['required', 'string', 'max:255'],
            'personal_number' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            // Photos are saved to the user's own Google Drive, so there is no limit on how many
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:10240', 'dimensions:max_width=8000,max_height=8000'],
            // Certificates, handwritten pedigrees and photos of relatives
            'pedigree_images' => ['nullable', 'array'],
            'pedigree_images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:10240', 'dimensions:max_width=8000,max_height=8000'],
            // Who each certificate or relative's photo shows and whether it is on the pedigree
            // (JSON list of {ref, relation, public})
            'pedigree_image_details' => ['nullable', 'json'],
            // Who bred the bird, when not both parents are in the user's loft
            'bred_by' => ['nullable', 'string', 'max:255'],
            // How many files the form sent, to catch any the server dropped (PHP's max_file_uploads)
            'upload_count' => ['nullable', 'integer', 'min:0'],
            'for_sale' => ['boolean'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'hide_price' => ['boolean'],
            'sale_description' => ['nullable', 'string'],
            'clutch_id' => [
                'nullable',
                'integer',
                Rule::exists('clutches', 'id')->where(fn ($q) => $q->whereIn(
                    'pairing_id',
                    \App\Models\Pairing::where('user_id', $this->user()?->id ?? 0)->select('id'),
                )),
            ],
            'location_id' => [
                'nullable',
                'integer',
                \Illuminate\Validation\Rule::exists('locations', 'id')->where(fn ($q) => $q->where('user_id', $this->user()?->id ?? 0)),
            ],

            'sire_id' => [
                'nullable',
                'integer',
                Rule::exists('pigeons', 'id')->where(fn ($query) => $query
                    ->where('user_id', $this->user()?->id ?? 0)
                    ->where('gender', 'male')
                ),
            ],
            'dam_id' => [
                'nullable',
                'integer',
                Rule::exists('pigeons', 'id')->where(fn ($query) => $query
                    ->where('user_id', $this->user()?->id ?? 0)
                    ->where('gender', 'female')
                ),
            ],

            'sire_name' => ['nullable', 'string', 'max:255'],
            'sire_ring_number' => ['nullable', 'string', 'max:255'],
            'sire_color' => ['nullable', 'string', 'max:255'],
            'sire_notes' => ['nullable', 'string'],

            'dam_name' => ['nullable', 'string', 'max:255'],
            'dam_ring_number' => ['nullable', 'string', 'max:255'],
            'dam_color' => ['nullable', 'string', 'max:255'],
            'dam_notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photos.*.dimensions' => 'Photos can be at most 8000 × 8000 pixels.',
            'photos.*.max' => 'Each photo can be at most 10 MB.',
            'photos.*.image' => 'Photos must be images.',
            'photos.*.mimes' => 'Photos must be JPEG, PNG or WebP images.',
            'pedigree_images.*.dimensions' => 'Certificates and photos of relatives can be at most 8000 × 8000 pixels.',
            'pedigree_images.*.max' => 'Each certificate or photo of a relative can be at most 10 MB.',
            'pedigree_images.*.image' => 'Certificates and photos of relatives must be images.',
            'pedigree_images.*.mimes' => 'Certificates and photos of relatives must be JPEG, PNG or WebP images.',
        ];
    }
}
