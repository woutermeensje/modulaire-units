<?php

namespace App\Http\Requests;

use App\Models\UsedListing;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UsedListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'location' => ['required', 'string', 'max:150'],
            'price' => ['required', 'integer', 'min:1', 'max:10000000'],
            'images' => ['required', 'array', 'min:1', 'max:'.UsedListing::MAX_IMAGES],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'contact_name' => ['required', 'string', 'max:150'],
            'contact_email' => ['required', 'email', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.max' => 'Je kunt maximaal '.UsedListing::MAX_IMAGES.' afbeeldingen uploaden.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'titel',
            'description' => 'beschrijving',
            'location' => 'locatie',
            'price' => 'prijs',
            'images' => 'afbeeldingen',
            'images.*' => 'afbeelding',
            'contact_name' => 'naam',
            'contact_email' => 'e-mailadres',
            'contact_phone' => 'telefoonnummer',
        ];
    }
}
