<?php

namespace App\Models;

use Database\Factories\TextQuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A quote an admin typed by hand in /account/tekst-offerte/ and sent to a client as a PDF: a title and a text made in
 * the rich text editor, without a composed unit. The text is kept as cleaned HTML (see RichText::clean()).
 */
#[Fillable(['client_id', 'created_by', 'title', 'body', 'sent_at'])]
class TextQuote extends Model
{
    /** @use HasFactory<TextQuoteFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The number as shown on the PDF, e.g. "OT-2026-0007". It has its own series, apart from the OF numbers of the
     * quote tool, so the two never share a number.
     */
    public function number(): string
    {
        return sprintf('OT-%s-%04d', ($this->created_at ?? now())->format('Y'), $this->id ?? 0);
    }
}
