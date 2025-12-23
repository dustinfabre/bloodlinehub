<?php

namespace App\Http\Requests;

use App\Models\Pigeon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePigeonRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Pigeon|null $pigeon */
        $pigeon = $this->route('pigeon');

        return $pigeon !== null && $this->user()?->id === $pigeon->user_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Pigeon|null $pigeon */
        $pigeon = $this->route('pigeon');
        $ignoreIds = array_filter([$pigeon?->id]);

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'in:male,female'],
            'hatch_date' => ['nullable', 'date'],
            'status' => ['required', 'in:alive,deceased,missing'],
            'pigeon_status' => ['required', 'in:racing,breeding,stock'],
            'race_type' => ['required', 'in:south,north,summer,olr,none'],
            'color' => ['nullable', 'string', 'max:255'],
            'ring_number' => ['required', 'string', 'max:255'],
            'personal_number' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['string'],
            'pedigree_image' => ['nullable', 'string', 'url'],
            'for_sale' => ['boolean'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'hide_price' => ['boolean'],
            'sale_description' => ['nullable', 'string'],

            'sire_id' => [
                'nullable',
                'integer',
                Rule::notIn($ignoreIds),
                Rule::exists('pigeons', 'id')->where(fn ($query) => $query
                    ->where('user_id', $this->user()?->id ?? 0)
                    ->where('gender', 'male')
                    ->whereIn('pigeon_status', ['breeding', 'racing'])),
            ],
            'dam_id' => [
                'nullable',
                'integer',
                Rule::notIn($ignoreIds),
                Rule::exists('pigeons', 'id')->where(fn ($query) => $query
                    ->where('user_id', $this->user()?->id ?? 0)
                    ->where('gender', 'female')
                    ->whereIn('pigeon_status', ['breeding', 'racing'])),
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
}
