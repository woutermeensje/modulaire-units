<?php

namespace App\Http\Requests;

use App\Support\RichText;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A quote typed by hand in /account/tekst-offerte/: a title, the text from the rich text editor and the client who
 * gets it. For "Voorbeeld PDF" the client may still be left empty.
 */
class TextQuoteRequest extends FormRequest
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
            'client_id' => [$this->routeIs('text-quotes.preview') ? 'nullable' : 'required', 'integer', Rule::exists('users', 'id')->where('is_admin', false)],
            'body' => ['required', 'string', 'max:100000', function (string $attribute, mixed $value, Closure $fail): void {
                if (RichText::isEmpty((string) $value)) {
                    $fail('Schrijf de tekst van de offerte.');
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Geef de offerte een titel.',
            'client_id.required' => 'Kies de opdrachtgever die de offerte krijgt.',
            'body.required' => 'Schrijf de tekst van de offerte.',
        ];
    }
}
