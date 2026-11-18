<?php

namespace App\Services;

use App\Models\Quote;
use App\Support\Locale;
use App\Support\QuoteSpecification;
use App\Support\UnitConfiguration;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Makes the quote PDF for a client and keeps the images of the unit that the browser made for it.
 *
 * The 3D views and the floor plan are made in the browser (see unit-configurator.js) and uploaded with the form:
 * dompdf can't draw the unit's SVG drawings itself.
 */
class QuotePdf
{
    /**
     * The PDF as a string, with the images given as data URIs or else the ones stored with the quote.
     *
     * @param  array<string, string>|null  $images  Data URIs by image key; see Quote::IMAGES.
     */
    public function render(Quote $quote, ?array $images = null): string
    {
        $images ??= $this->storedImages($quote);

        // Written in the language chosen for the quote, dates included.
        return Locale::using($quote->locale ?? Locale::SOURCE, fn (): string => Pdf::loadHTML($this->html($quote, $images))->setPaper('a4')->output());
    }

    /**
     * The HTML the PDF is made from, in the active language.
     *
     * @param  array<string, string>  $images  Data URIs by image key; see Quote::IMAGES.
     */
    public function html(Quote $quote, array $images = []): string
    {
        return view('pdf.quote', [
            'quote' => $quote,
            'specification' => (new QuoteSpecification(UnitConfiguration::normalize($quote->configuration), $quote->specifications ?? []))->sections(),
            'images' => $images,
            'logo' => $this->logo(),
        ])->render();
    }

    /**
     * The icon of the logo as a data URI. It lives in resources/ and not in public/, as public files are not always on
     * the server that makes the PDF; without it the PDF is made with the name only.
     */
    private function logo(): ?string
    {
        $path = resource_path('images/quote-logo.png');

        return is_file($path) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path)) : null;
    }

    /**
     * The uploaded images as data URIs, for a preview that is not saved.
     *
     * @param  array<string, UploadedFile>  $files
     * @return array<string, string>
     */
    public function dataUris(array $files): array
    {
        $images = [];
        foreach (array_keys(Quote::IMAGES) as $key) {
            if (isset($files[$key])) {
                $images[$key] = 'data:'.$files[$key]->getMimeType().';base64,'.base64_encode((string) $files[$key]->get());
            }
        }

        return $images;
    }

    /**
     * Store the uploaded images with the quote, on the private local disk.
     *
     * @param  array<string, UploadedFile>  $files
     */
    public function storeImages(Quote $quote, array $files): void
    {
        $paths = [];
        foreach (array_keys(Quote::IMAGES) as $key) {
            if (isset($files[$key])) {
                $paths[$key] = $files[$key]->storeAs("quotes/{$quote->id}", $key.'.'.($files[$key]->guessExtension() ?? 'png'), 'local');
            }
        }

        $quote->update(['images' => $paths ?: null]);
    }

    /**
     * @return array<string, string>
     */
    private function storedImages(Quote $quote): array
    {
        $images = [];
        foreach ($quote->images ?? [] as $key => $path) {
            if (Storage::disk('local')->exists($path)) {
                $images[$key] = 'data:'.Storage::disk('local')->mimeType($path).';base64,'.base64_encode((string) Storage::disk('local')->get($path));
            }
        }

        return $images;
    }
}
