<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Unit;
use App\Support\UnitConfiguration;
use App\Support\UnitRenderer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Keeps the rendered images of a unit's configuration in the media library in step with the configuration.
 *
 * A unit without a photo of its own gets the front view as its featured image, so cards, related units
 * and the quote show it too. Otherwise the renders are appended to the gallery by Unit::images().
 *
 * The renders are 2D drawings made by UnitRenderer, or 3D photos made in the admin's browser. The server
 * cannot make 3D photos itself, so they are kept for as long as the configuration they show is unchanged.
 */
class UnitConfigurationImages
{
    private const DIRECTORY = 'media/units';

    private const PHOTO_MARKER = '-3d-';

    public function __construct(private UnitRenderer $renderer, private MediaUploader $uploader) {}

    /**
     * Whether the given renders are 3D photos rather than 2D drawings.
     *
     * @param  list<string>|null  $paths
     */
    public static function usesPhotos(?array $paths): bool
    {
        return collect($paths ?? [])->contains(fn (string $path): bool => str_contains($path, self::PHOTO_MARKER));
    }

    /**
     * @param  array<string, UploadedFile>  $photos  New 3D photos keyed by view, see UnitRenderer::VIEWS.
     * @param  bool  $keepPhotos  Keep earlier 3D photos of an unchanged configuration instead of drawing it again.
     */
    public function sync(Unit $unit, array $photos = [], bool $keepPhotos = true): void
    {
        $previous = $unit->configuration_images ?? [];
        $current = [];

        if ($unit->configuration !== null) {
            foreach (array_keys(UnitRenderer::VIEWS) as $view) {
                $current[] = match (true) {
                    isset($photos[$view]) => $this->storePhoto($unit, $view, $photos[$view]),
                    $keepPhotos => $this->previousPhoto($unit, $view, $previous) ?? $this->store($unit, $view, $previous),
                    default => $this->store($unit, $view, $previous),
                };
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
        $this->register($unit, $view, $path, 'image/svg+xml', strlen($svg));

        return $path;
    }

    /**
     * Store a 3D photo with size variants. The file name contains a hash of the configuration it shows,
     * so a later sync can tell whether the photo is still up to date.
     */
    private function storePhoto(Unit $unit, string $view, UploadedFile $photo): string
    {
        $contents = (string) $photo->get();
        $path = self::DIRECTORY.'/'.$unit->slug.'-'.$view.self::PHOTO_MARKER.$this->configurationHash($unit).'-'.substr(md5($contents), 0, 10).'.jpg';

        Storage::disk('public')->put($path, $contents);
        $this->uploader->generateSizes($this->register($unit, $view, $path, 'image/jpeg', strlen($contents)));

        return $path;
    }

    /**
     * The earlier 3D photo of this view, if it shows the current configuration.
     *
     * @param  list<string>  $previous
     */
    private function previousPhoto(Unit $unit, string $view, array $previous): ?string
    {
        $marker = '-'.$view.self::PHOTO_MARKER.$this->configurationHash($unit).'-';

        return collect($previous)->first(
            fn (string $path): bool => str_contains($path, $marker) && Storage::disk('public')->exists($path),
        );
    }

    private function configurationHash(Unit $unit): string
    {
        return substr(md5((string) json_encode(UnitConfiguration::normalize($unit->configuration))), 0, 10);
    }

    private function register(Unit $unit, string $view, string $path, string $mimeType, int $size): Media
    {
        return Media::query()->updateOrCreate(['path' => $path], [
            'disk' => 'public',
            'title' => $unit->title.' – '.mb_strtolower(UnitRenderer::VIEWS[$view]['label']),
            'alt' => $unit->title,
            'mime_type' => $mimeType,
            'size' => $size,
        ]);
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
