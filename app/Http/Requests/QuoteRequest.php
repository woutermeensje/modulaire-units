<?php

namespace App\Http\Requests;

use App\Models\Quote;
use App\Support\Locale;
use App\Support\QuotePrice;
use App\Support\QuoteSpecification;
use App\Support\UnitConfiguration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A quote from /account/offerte-samenstellen/: title, client, accompanying text, the composed unit and the images of
 * the unit the browser made for the PDF, plus sale or rent, project details, the technical specification per section,
 * price lines, terms, open points and internal notes.
 */
class QuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * Price lines without a description are the empty rows of the form: they are left out.
     */
    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');
        if (is_array($lines)) {
            $this->merge(['lines' => array_values(array_filter($lines, fn (mixed $line): bool => is_array($line) && filled($line['description'] ?? null)))]);
        }
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
            'kind' => ['nullable', Rule::in(array_keys(Quote::KINDS))],
            'rental_weeks' => ['nullable', 'integer', 'min:1', 'max:520'],
            'reference' => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'delivery_date' => ['nullable', 'string', 'max:100'],
            'valid_until' => ['nullable', 'date'],
            'intro' => ['nullable', 'string', 'max:10000'],
            'specifications' => ['nullable', 'array:'.implode(',', array_keys(QuoteSpecification::DETAILS))],
            'specifications.*' => ['nullable', 'string', 'max:5000'],
            'lines' => ['nullable', 'array', 'max:50'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
            // A line that is charged or offered as an option needs a price; otherwise choose "Nog te bepalen".
            'lines.*.price' => ['nullable', 'numeric', 'min:0', 'max:10000000', 'required_if:lines.*.status,price,optional'],
            'lines.*.period' => ['required', Rule::in(array_keys(QuotePrice::PERIODS))],
            'lines.*.status' => ['required', Rule::in(array_keys(QuotePrice::STATUSES))],
            'terms' => ['nullable', 'string', 'max:10000'],
            'open_points' => ['nullable', 'string', 'max:10000'],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
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
            'kind' => 'koop of huur',
            'rental_weeks' => 'huurperiode',
            'valid_until' => 'geldig tot',
            'lines.*.description' => 'omschrijving',
            'lines.*.quantity' => 'aantal',
            'lines.*.price' => 'prijs',
            'lines.*.status' => 'status',
            'configuration.size' => 'afmeting',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.*.price.required_if' => 'Vul bij elke berekende of optionele prijsregel een prijs in, of kies "Nog te bepalen".',
        ];
    }
}
