<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Unit;
use App\Support\UnitRenderer;
use Illuminate\Support\Facades\Storage;

/**
 * Keeps the rendered images of a unit's configuration in the media library in step with the configuration.
 *
 * A unit without a photo of its own gets the front view as its featured image, so cards, related units
 * and the quote show it too. Otherwise the renders are appended to the gallery by Unit::images().
 */
class UnitConfigurationImages
{
    private const DIRECTORY = 'media/units';

    public function __construct(private UnitRenderer $renderer) {}

    public function sync(Unit $unit): void
    {
        $previous = $unit->configuration_images ?? [];
        $current = [];

        if ($unit->configuration !== null) {
            foreach (array_keys(UnitRenderer::VIEWS) as $view) {
                $current[] = $this->store($unit, $view, $previous);
            }
        }

        foreach (array_diff($previous, $current) as $path) {
            $this->delete($path);
        }

        if (blank($unit->featured_image) || in_array($unit->featured_image, $previous, true)) {
            $unit->featured_image = $current[0] ?? null;
        }

        $unit->configuration_images = $current === [] ? null : $current;
        $unit->saveQuietly();
    }

    /**
     * Write the view to the public disk unless an identical render is already there. The file name
     * contains a hash of the contents, so browsers and CDNs never serve an outdated render.
     *
     * @param  list<string>  $previous
     */
    private function store(Unit $unit, string $view, array $previous): string
    {
        $svg = $this->renderer->renderView($unit->configuration, $view);
        $path = self::DIRECTORY.'/'.$unit->slug.'-'.$view.'-'.substr(md5($svg), 0, 10).'.svg';

        if (in_array($path, $previous, true) && Storage::disk('public')->exists($path)) {
            return $path;
        }

        Storage::disk('public')->put($path, $svg);
        Media::query()->updateOrCreate(['path' => $path], [
            'disk' => 'public',
            'title' => $unit->title.' – '.mb_strtolower(UnitRenderer::VIEWS[$view]['label']),
            'alt' => $unit->title,
            'mime_type' => 'image/svg+xml',
            'size' => strlen($svg),
        ]);

        return $path;
    }

    private function delete(string $path): void
    {
        $media = Media::query()->where('path', $path)->first();

        if ($media !== null) {
            $media->delete();
        } else {
            Storage::disk('public')->delete($path);
        }
    }
}
