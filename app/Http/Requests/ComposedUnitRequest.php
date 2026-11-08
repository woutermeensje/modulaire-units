<?php

namespace App\Http\Requests;

use App\Support\UnitConfiguration;
use App\Support\UnitRenderer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An admin saves a unit composed in /account/unit-samenstellen/ as a product (Unit) for the landing pages.
 */
class ComposedUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price_rent_week' => ['nullable', 'string', 'max:190'],
            'photos_3d_enabled' => ['nullable', 'boolean'],
            'photos_3d' => ['nullable', 'array:'.implode(',', array_keys(UnitRenderer::VIEWS))],
            'photos_3d.*' => ['image', 'mimes:jpg,jpeg', 'max:10240'],
            ...UnitConfiguration::rules('configuration'),
            'configuration.size' => ['required', Rule::in(array_keys(UnitConfiguration::SIZES))],
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
            'price_rent_week' => 'huurprijs per week',
            'configuration.size' => 'afmeting',
        ];
    }
}
