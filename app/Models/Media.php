<?php

namespace App\Models;

use App\Support\SiteUrl;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['wp_id', 'path', 'disk', 'title', 'alt', 'caption', 'mime_type', 'width', 'height', 'size', 'sizes'])]
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    protected $table = 'media';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sizes' => 'array',
        ];
    }

    public function url(?string $size = null): string
    {
        $path = $size !== null ? ($this->sizes[$size] ?? $this->path) : $this->path;

        return SiteUrl::media($path);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    /**
     * Find the best matching size variant for a stored path, e.g. "large" for card images.
     */
    public static function variant(?string $path, string $size): ?string
    {
        if ($path === null) {
            return null;
        }

        $media = static::query()->where('path', $path)->first();

        return $media?->sizes[$size] ?? $path;
    }

    protected static function booted(): void
    {
        static::deleting(function (Media $media): void {
            $disk = Storage::disk($media->disk);
            $disk->delete(array_values(array_unique(array_merge([$media->path], array_values($media->sizes ?? [])))));
        });
    }
}
