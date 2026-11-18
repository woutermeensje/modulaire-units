<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * A quote saved as a draft: the same fields as a quote that is sent, but only the title is needed. The client and the
 * prices may still be missing; they are checked once the quote is sent.
 */
class DraftQuoteRequest extends QuoteRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'client_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_admin', false)],
            'lines.*.price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ];
    }
}
