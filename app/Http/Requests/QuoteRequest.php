<?php

namespace App\Http\Requests;

use App\Models\Quote;
use App\Support\Locale;
use App\Support\UnitConfiguration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A quote from /account/offerte-samenstellen/: title, client, accompanying text, the composed unit and the images of
 * the unit the browser made for the PDF.
 */
class QuoteRequest extends FormRequest
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
            'client_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_admin', false)],
            'locale' => ['required', Rule::in(Locale::all())],
            'intro' => ['nullable', 'string', 'max:10000'],
            'images' => ['nullable', 'array:'.implode(',', array_keys(Quote::IMAGES))],
            'images.*' => ['image', 'mimes:jpg,jpeg,png', 'max:10240'],
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
            'client_id' => 'opdrachtgever',
            'locale' => 'taal',
            'intro' => 'begeleidende tekst',
            'configuration.size' => 'afmeting',
        ];
    }
}
