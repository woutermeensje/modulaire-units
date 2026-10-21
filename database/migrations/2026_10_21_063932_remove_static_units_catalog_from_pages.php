<?php

use App\Models\Page;
use Dom\HTMLDocument;
use Illuminate\Database\Migrations\Migration;

/**
 * Removes the units overview that the WordPress import stored as static HTML in page content (e.g. /woonunits/).
 */
return new class extends Migration
{
    public function up(): void
    {
        Page::query()->where('content', 'like', '%mh-units-catalog%')->each(function (Page $page): void {
            $document = HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$page->content.'</body></html>', LIBXML_NOERROR);

            foreach ($document->querySelectorAll('.mh-units-catalog__intro, .mh-units-catalog') as $element) {
                $element->remove();
            }

            $page->update(['content' => trim($document->body->innerHTML)]);
        });
    }

    public function down(): void
    {
        // The removed overview was a static copy of the old WordPress page and is not restored.
    }
};
