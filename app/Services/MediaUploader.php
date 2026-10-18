<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores uploads on the public disk under media/{year}/{month}/ and creates the same size
 * variants WordPress used (thumbnail, medium, medium_large, large), so templates can pick a size.
 */
class MediaUploader
{
    /** @var array<string, array{0: int, 1: int, 2: bool}> size => [width, height, crop] */
    public const SIZES = [
        'thumbnail' => [150, 150, true],
        'medium' => [300, 300, false],
        'medium_large' => [768, 0, false],
        'large' => [1024, 1024, false],
    ];

    public function store(UploadedFile $file, ?string $alt = null): string
    {
        $directory = 'media/'.now()->format('Y/m');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $basename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'bestand';
        $filename = $this->uniqueName($directory, $basename, $extension);

        $path = $file->storeAs($directory, $filename, 'public');
        $mime = $file->getMimeType();

        $media = Media::query()->create([
            'path' => $path,
            'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'alt' => $alt,
            'mime_type' => $mime,
            'size' => $file->getSize(),
        ]);

        if (str_starts_with((string) $mime, 'image/') && $mime !== 'image/svg+xml') {
            $this->generateSizes($media);
        }

        return $path;
    }

    public function generateSizes(Media $media): void
    {
        $disk = Storage::disk('public');
        $absolute = $disk->path($media->path);
        $info = @getimagesize($absolute);

        if ($info === false) {
            return;
        }

        [$width, $height] = $info;
        $source = $this->load($absolute, $info[2]);

        if ($source === null) {
            return;
        }

        $sizes = [];
        foreach (self::SIZES as $name => [$maxWidth, $maxHeight, $crop]) {
            [$targetWidth, $targetHeight, $srcX, $srcY, $srcWidth, $srcHeight] = $this->dimensions($width, $height, $maxWidth, $maxHeight, $crop);

            if ($targetWidth >= $width && $targetHeight >= $height) {
                continue;
            }

            $variantPath = preg_replace('/(\.[a-z0-9]+)$/i', "-{$targetWidth}x{$targetHeight}$1", $media->path);
            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagecopyresampled($canvas, $source, 0, 0, $srcX, $srcY, $targetWidth, $targetHeight, $srcWidth, $srcHeight);
            $this->save($canvas, $disk->path($variantPath), $info[2]);
            imagedestroy($canvas);

            $sizes[$name] = $variantPath;
        }

        imagedestroy($source);

        $media->update(['width' => $width, 'height' => $height, 'sizes' => $sizes]);
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int}
     */
    private function dimensions(int $width, int $height, int $maxWidth, int $maxHeight, bool $crop): array
    {
        if ($crop) {
            $ratio = max($maxWidth / $width, $maxHeight / $height);
            $srcWidth = (int) round($maxWidth / $ratio);
            $srcHeight = (int) round($maxHeight / $ratio);

            return [$maxWidth, $maxHeight, (int) (($width - $srcWidth) / 2), (int) (($height - $srcHeight) / 2), $srcWidth, $srcHeight];
        }

        $ratio = min($maxWidth / $width, $maxHeight > 0 ? $maxHeight / $height : INF, 1);

        return [(int) round($width * $ratio), (int) round($height * $ratio), 0, 0, $width, $height];
    }

    private function load(string $path, int $type): ?\GdImage
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            default => false,
        };

        return $image ?: null;
    }

    private function save(\GdImage $image, string $path, int $type): void
    {
        match ($type) {
            IMAGETYPE_PNG => imagepng($image, $path, 8),
            IMAGETYPE_WEBP => imagewebp($image, $path, 82),
            IMAGETYPE_GIF => imagegif($image, $path),
            default => imagejpeg($image, $path, 82),
        };
    }

    private function uniqueName(string $directory, string $basename, string $extension): string
    {
        $name = "{$basename}.{$extension}";
        $counter = 1;

        while (Storage::disk('public')->exists("{$directory}/{$name}")) {
            $name = "{$basename}-".$counter++.".{$extension}";
        }

        return $name;
    }
}
