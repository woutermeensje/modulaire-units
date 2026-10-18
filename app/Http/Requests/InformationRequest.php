<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InformationRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'request_type' => ['required', 'in:Huur,Koop,Gebruikt'],
            'message' => ['required', 'string', 'max:5000'],
            'source_url' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'voornaam',
            'last_name' => 'achternaam',
            'email' => 'e-mailadres',
            'phone' => 'telefoonnummer',
            'request_type' => 'type verzoek',
            'message' => 'vraag',
        ];
    }
}
